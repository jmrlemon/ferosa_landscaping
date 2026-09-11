<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use JsonException;

/**
 * Structural validation for uploaded GLB (binary glTF 2.0) product models.
 *
 * An upload replaces a model that customers are already viewing in AR, so a
 * file that merely *looks* like a GLB is not good enough: a container can be
 * perfectly well-formed and still place an invisible object in the customer's
 * garden. This parses the container itself - header, chunk table, embedded
 * JSON, buffer lengths, and the reachable scene geometry - and refuses anything
 * that would not render.
 *
 * Each method returns null when the model is acceptable, or a message written
 * for the admin uploading it.
 *
 * Extracted from AdminController, where it was ~350 lines of binary parsing
 * sitting in the middle of the dashboard, order and product code.
 */
class GlbValidator
{
    /** 'JSON' as a little-endian chunk type. */
    private const JSON_CHUNK_TYPE = 0x4E4F534A;

    /** 'BIN\0' as a little-endian chunk type. */
    private const BIN_CHUNK_TYPE = 0x004E4942;

    /** Cap on the JSON chunk we are willing to decode into memory. */
    private const JSON_MAX_BYTES = 8 * 1024 * 1024;

    /** Below this the model has no usable height and cannot be scaled. */
    private const MIN_HEIGHT_UNITS = 0.000001;

    private const TRIANGLE_WARN_LIMIT = 100000;

    private const TRIANGLE_HARD_LIMIT = 250000;

    private const TEXTURE_WARN_EDGE = 2048;

    private const TEXTURE_HARD_EDGE = 4096;

    private const TEXTURE_WARN_DECODED_BYTES = 48 * 1024 * 1024;

    private const FILE_WARN_BYTES = 8 * 1024 * 1024;

    /** @var list<string> */
    private const SUPPORTED_REQUIRED_EXTENSIONS = [
        'KHR_draco_mesh_compression',
        'EXT_meshopt_compression',
        'EXT_mesh_gpu_instancing',
        'KHR_lights_punctual',
        'KHR_materials_clearcoat',
        'KHR_materials_emissive_strength',
        'KHR_materials_ior',
        'KHR_materials_iridescence',
        'KHR_materials_pbrSpecularGlossiness',
        'KHR_materials_sheen',
        'KHR_materials_specular',
        'KHR_materials_transmission',
        'KHR_materials_unlit',
        'KHR_materials_variants',
        'KHR_materials_volume',
        'KHR_texture_basisu',
        'KHR_texture_transform',
    ];

    private const MESHOPT_EXTENSION = 'EXT_meshopt_compression';

    /**
     * Validate the GLB container before it can replace a working product model.
     *
     * @param  list<string>  $warnings
     */
    public function validate(UploadedFile $file, array &$warnings = []): ?string
    {
        $warnings = [];
        $path = $file->getRealPath();
        $actualLength = $file->getSize();

        if (! is_string($path) || $path === '' || ! is_int($actualLength) || $actualLength < 28) {
            return 'This GLB is incomplete. Upload a complete, self-contained GLB 2.0 file.';
        }

        $handle = @fopen($path, 'rb');
        if (! is_resource($handle)) {
            return 'The GLB could not be read. Please export it again and retry.';
        }

        try {
            $header = $this->readBytes($handle, 12);
            if ($header === null || substr($header, 0, 4) !== 'glTF') {
                return 'This file is not a valid binary GLB model.';
            }

            $headerValues = unpack('Vversion/Vlength', substr($header, 4));
            if (! is_array($headerValues) || (int) ($headerValues['version'] ?? 0) !== 2) {
                return 'This model must use the GLB 2.0 format.';
            }

            $declaredLength = (int) ($headerValues['length'] ?? 0);
            if ($declaredLength !== $actualLength) {
                return 'The GLB file length is inconsistent. Export the model again before uploading.';
            }

            $offset = 12;
            $chunkIndex = 0;
            $jsonDocument = null;
            $binChunkLength = null;
            $binChunkOffset = null;

            while ($offset < $declaredLength) {
                if (($declaredLength - $offset) < 8) {
                    return 'The GLB contains a truncated chunk header.';
                }

                $chunkHeader = $this->readBytes($handle, 8);
                if ($chunkHeader === null) {
                    return 'The GLB contains a truncated chunk header.';
                }

                $chunkValues = unpack('Vlength/Vtype', $chunkHeader);
                if (! is_array($chunkValues)) {
                    return 'The GLB chunk table could not be read.';
                }

                $chunkLength = (int) ($chunkValues['length'] ?? -1);
                $chunkType = (int) ($chunkValues['type'] ?? -1);
                $offset += 8;

                if ($chunkLength < 0 || ($chunkLength % 4) !== 0) {
                    return 'The GLB contains a chunk with an invalid length.';
                }

                if ($chunkLength > ($declaredLength - $offset)) {
                    return 'The GLB contains truncated chunk data.';
                }

                if ($chunkIndex === 0 && $chunkType !== self::JSON_CHUNK_TYPE) {
                    return 'The first GLB chunk must contain the model JSON.';
                }

                if ($chunkType === self::JSON_CHUNK_TYPE) {
                    if ($chunkIndex !== 0 || $jsonDocument !== null) {
                        return 'The GLB contains an unexpected additional JSON chunk.';
                    }

                    if ($chunkLength === 0 || $chunkLength > self::JSON_MAX_BYTES) {
                        return 'The GLB model JSON is empty or too large to validate safely.';
                    }

                    $jsonChunk = $this->readBytes($handle, $chunkLength);
                    if ($jsonChunk === null) {
                        return 'The GLB contains truncated model JSON.';
                    }

                    try {
                        $jsonDocument = json_decode(rtrim($jsonChunk, " \t\r\n"), true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        return 'The GLB contains invalid model JSON.';
                    }

                    if (! is_array($jsonDocument)) {
                        return 'The GLB model JSON must be an object.';
                    }
                } else {
                    if (! $this->skipBytes($handle, $chunkLength)) {
                        return 'The GLB contains truncated chunk data.';
                    }

                    if ($chunkType === self::BIN_CHUNK_TYPE) {
                        if ($binChunkLength !== null) {
                            return 'The GLB contains more than one binary data chunk.';
                        }

                        $binChunkLength = $chunkLength;
                        $binChunkOffset = $offset;
                    }
                }

                $offset += $chunkLength;
                $chunkIndex++;
            }

            if ($offset !== $declaredLength || $jsonDocument === null) {
                return 'The GLB container is incomplete.';
            }

            if ($binChunkLength === null || $binChunkOffset === null) {
                return 'The GLB must include its binary model data in a BIN chunk.';
            }

            return $this->validateResources(
                $jsonDocument,
                $binChunkLength,
                $handle,
                $binChunkOffset,
                $actualLength,
                $warnings,
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function readBytes($handle, int $length): ?string
    {
        if ($length === 0) {
            return '';
        }

        $data = '';
        while (strlen($data) < $length && ! feof($handle)) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }

            $data .= $part;
        }

        return strlen($data) === $length ? $data : null;
    }

    /**
     * @param  resource  $handle
     */
    private function skipBytes($handle, int $length): bool
    {
        $remaining = $length;
        while ($remaining > 0) {
            $part = fread($handle, min($remaining, 1024 * 1024));
            if ($part === false || $part === '') {
                return false;
            }

            $remaining -= strlen($part);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  resource  $handle
     * @param  list<string>  $warnings
     */
    private function validateResources(
        array $document,
        int $binChunkLength,
        $handle,
        int $binChunkOffset,
        int $fileSize,
        array &$warnings = [],
    ): ?string {
        if ($fileSize > self::FILE_WARN_BYTES) {
            $warnings[] = "The GLB file is {$fileSize} bytes; the recommended budget is ".self::FILE_WARN_BYTES.' bytes.';
        }

        $assetVersion = data_get($document, 'asset.version');
        if (! is_string($assetVersion) || ! str_starts_with($assetVersion, '2.')) {
            return 'The GLB model JSON must declare glTF asset version 2.x.';
        }

        $extensionsRequired = $document['extensionsRequired'] ?? [];
        if (! is_array($extensionsRequired)) {
            return 'The GLB extensionsRequired list is invalid.';
        }

        foreach ($extensionsRequired as $extension) {
            if (! is_string($extension) || trim($extension) === '') {
                return 'The GLB extensionsRequired list contains an invalid extension name.';
            }

            if (! in_array($extension, self::SUPPORTED_REQUIRED_EXTENSIONS, true)) {
                return "The GLB requires unsupported extension \"{$extension}\". Remove it or export the model without requiring it.";
            }

            if ($extension === self::MESHOPT_EXTENSION) {
                $warnings[] = 'The GLB requires EXT_meshopt_compression. Verify this asset on a physical ARCore phone before publishing it.';
            }
        }

        foreach (['buffers', 'images'] as $resourceType) {
            $resources = $document[$resourceType] ?? [];
            if (! is_array($resources)) {
                return "The GLB {$resourceType} list is invalid.";
            }

            foreach ($resources as $resource) {
                if (! is_array($resource) || ! array_key_exists('uri', $resource)) {
                    continue;
                }

                $uri = $resource['uri'];
                if (! is_string($uri) || ! str_starts_with(strtolower(trim($uri)), 'data:')) {
                    return 'The GLB references an external file. Embed all buffers and images before uploading.';
                }
            }
        }

        $buffers = $document['buffers'] ?? null;
        if (! is_array($buffers) || $buffers === [] || ! is_array($buffers[0] ?? null)) {
            return 'The GLB model JSON must describe its embedded binary buffer.';
        }

        $embeddedBufferLength = $buffers[0]['byteLength'] ?? null;
        if (! is_int($embeddedBufferLength) || $embeddedBufferLength < 0) {
            return 'The GLB embedded buffer length is invalid.';
        }

        // A GLB BIN chunk can contain up to three trailing padding bytes.
        if ($embeddedBufferLength > $binChunkLength || ($binChunkLength - $embeddedBufferLength) > 3) {
            return 'The GLB binary chunk does not match the buffer length declared by the model.';
        }

        if ($geometryError = $this->validateRenderableGeometry($document, $warnings)) {
            return $geometryError;
        }

        return $this->validateTextures(
            $document,
            $handle,
            $binChunkOffset,
            $binChunkLength,
            $warnings,
        );
    }

    /**
     * Inspect image headers in the embedded BIN chunk without loading entire textures into memory.
     *
     * @param  array<string, mixed>  $document
     * @param  resource  $handle
     * @param  list<string>  $warnings
     */
    private function validateTextures(
        array $document,
        $handle,
        int $binChunkOffset,
        int $binChunkLength,
        array &$warnings = [],
    ): ?string {
        $images = $document['images'] ?? [];
        if ($images === []) {
            return null;
        }
        if (! is_array($images)) {
            return 'The GLB images list is invalid.';
        }

        $bufferViews = $document['bufferViews'] ?? [];
        if (! is_array($bufferViews)) {
            return 'The GLB bufferViews list is invalid.';
        }

        $largestTextureEdge = 0;
        $decodedTextureBytes = 0.0;

        foreach ($images as $imageIndex => $image) {
            if (! is_array($image)) {
                return 'The GLB contains an invalid image entry.';
            }

            // Data URIs are self-contained but are not part of the BIN header scan. The existing
            // URI validation still ensures they do not reference an external file.
            if (! array_key_exists('bufferView', $image)) {
                continue;
            }

            $bufferViewIndex = $image['bufferView'];
            if (! is_int($bufferViewIndex) || ! is_array($bufferViews[$bufferViewIndex] ?? null)) {
                return "The GLB image {$imageIndex} references an invalid bufferView.";
            }

            $bufferView = $bufferViews[$bufferViewIndex];
            $byteOffset = $bufferView['byteOffset'] ?? 0;
            $byteLength = $bufferView['byteLength'] ?? null;
            if (! is_int($byteOffset) || $byteOffset < 0 ||
                ! is_int($byteLength) || $byteLength <= 0 ||
                $byteOffset > $binChunkLength || $byteLength > ($binChunkLength - $byteOffset)) {
                return "The GLB image {$imageIndex} bufferView is outside the embedded binary data.";
            }

            $dimensions = $this->readImageDimensions(
                $handle,
                $binChunkOffset,
                $binChunkLength,
                $byteOffset,
                $byteLength,
            );
            if ($dimensions === null) {
                continue;
            }

            [$width, $height] = $dimensions;
            $largestTextureEdge = max($largestTextureEdge, $width, $height);
            if ($largestTextureEdge > self::TEXTURE_HARD_EDGE) {
                return "The GLB texture {$imageIndex} is {$width}x{$height}px; the maximum supported edge is ".self::TEXTURE_HARD_EDGE.'px. Resize it before uploading.';
            }

            $decodedTextureBytes += ((float) $width * $height * 4 * 4) / 3;
        }

        if ($largestTextureEdge > self::TEXTURE_WARN_EDGE) {
            $warnings[] = "The largest GLB texture edge is {$largestTextureEdge}px; the recommended budget is ".self::TEXTURE_WARN_EDGE.'px.';
        }

        if ($decodedTextureBytes > self::TEXTURE_WARN_DECODED_BYTES) {
            $decodedMegabytes = number_format(
                $decodedTextureBytes / (1024 * 1024),
                2,
                '.',
                '',
            );
            $warnings[] = "The GLB textures require approximately {$decodedMegabytes} MB decoded memory; the recommended budget is 48 MB.";
        }

        return null;
    }

    /**
     * @param  resource  $handle
     * @return array{0: int, 1: int}|null
     */
    private function readImageDimensions(
        $handle,
        int $binChunkOffset,
        int $binChunkLength,
        int $imageOffset,
        int $imageLength,
    ): ?array {
        $headerLength = min(32, $imageLength);
        $header = $this->readBinBytes(
            $handle,
            $binChunkOffset,
            $binChunkLength,
            $imageOffset,
            $headerLength,
        );
        if ($header === null) {
            return null;
        }

        if (strlen($header) >= 24 && substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $pngValues = unpack('Nwidth/Nheight', substr($header, 16, 8));
            if (is_array($pngValues) && (int) ($pngValues['width'] ?? 0) > 0 && (int) ($pngValues['height'] ?? 0) > 0) {
                return [(int) $pngValues['width'], (int) $pngValues['height']];
            }

            return null;
        }

        if (substr($header, 0, 2) !== "\xFF\xD8") {
            return null;
        }

        $position = 2;
        $sofMarkers = [
            0xC0, 0xC1, 0xC2, 0xC3,
            0xC5, 0xC6, 0xC7, 0xC9,
            0xCA, 0xCB, 0xCD, 0xCE, 0xCF,
        ];

        while ($position + 4 <= $imageLength) {
            $markerBytes = $this->readBinBytes(
                $handle,
                $binChunkOffset,
                $binChunkLength,
                $imageOffset + $position,
                2,
            );
            if ($markerBytes === null || ord($markerBytes[0]) !== 0xFF) {
                return null;
            }

            $marker = ord($markerBytes[1]);
            $position += 2;
            while ($marker === 0xFF && $position < $imageLength) {
                $markerByte = $this->readBinBytes(
                    $handle,
                    $binChunkOffset,
                    $binChunkLength,
                    $imageOffset + $position,
                    1,
                );
                if ($markerByte === null) {
                    return null;
                }

                $marker = ord($markerByte[0]);
                $position++;
            }

            $isStandaloneMarker = $marker === 0xD8 || $marker === 0xD9 || $marker === 0xDA ||
                ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01;
            if ($isStandaloneMarker) {
                if ($marker === 0xD9 || $marker === 0xDA) {
                    return null;
                }

                continue;
            }

            $lengthBytes = $this->readBinBytes(
                $handle,
                $binChunkOffset,
                $binChunkLength,
                $imageOffset + $position,
                2,
            );
            if ($lengthBytes === null) {
                return null;
            }

            $segmentValues = unpack('nlength', $lengthBytes);
            $segmentLength = (int) ($segmentValues['length'] ?? 0);
            if ($segmentLength < 2 || $position + $segmentLength > $imageLength) {
                return null;
            }

            if (in_array($marker, $sofMarkers, true)) {
                $sizeBytes = $this->readBinBytes(
                    $handle,
                    $binChunkOffset,
                    $binChunkLength,
                    $imageOffset + $position + 3,
                    4,
                );
                if ($sizeBytes === null) {
                    return null;
                }

                $sizeValues = unpack('nheight/nwidth', $sizeBytes);
                if (is_array($sizeValues) && (int) ($sizeValues['width'] ?? 0) > 0 && (int) ($sizeValues['height'] ?? 0) > 0) {
                    return [(int) $sizeValues['width'], (int) $sizeValues['height']];
                }

                return null;
            }

            $position += $segmentLength;
        }

        return null;
    }

    /**
     * @param  resource  $handle
     */
    private function readBinBytes(
        $handle,
        int $binChunkOffset,
        int $binChunkLength,
        int $relativeOffset,
        int $length,
    ): ?string {
        if ($relativeOffset < 0 || $length < 0 || $relativeOffset > $binChunkLength ||
            $length > ($binChunkLength - $relativeOffset) || fseek($handle, $binChunkOffset + $relativeOffset) !== 0) {
            return null;
        }

        return $this->readBytes($handle, $length);
    }

    /**
     * Ensure the default scene contains a reachable mesh with finite, non-zero Y bounds.
     *
     * SceneView uses POSITION accessor bounds to calculate the runtime model bounding box. A GLB
     * container can be structurally valid while containing no visible scene geometry, which would
     * otherwise produce a successful upload followed by an invisible AR placement.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $warnings
     */
    private function validateRenderableGeometry(array $document, array &$warnings = []): ?string
    {
        $scenes = $document['scenes'] ?? null;
        $nodes = $document['nodes'] ?? null;
        $meshes = $document['meshes'] ?? null;
        $accessors = $document['accessors'] ?? null;

        if (! is_array($scenes) || $scenes === [] ||
            ! is_array($nodes) || $nodes === [] ||
            ! is_array($meshes) || $meshes === [] ||
            ! is_array($accessors) || $accessors === []) {
            return 'The GLB must contain a scene with visible mesh geometry.';
        }

        $sceneIndex = $document['scene'] ?? 0;
        if (! is_int($sceneIndex) || ! is_array($scenes[$sceneIndex] ?? null)) {
            return 'The GLB default scene is invalid.';
        }

        $rootNodes = $scenes[$sceneIndex]['nodes'] ?? null;
        if (! is_array($rootNodes) || $rootNodes === []) {
            return 'The GLB default scene does not contain any nodes.';
        }

        $pendingNodeIndexes = array_values($rootNodes);
        $visitedNodeIndexes = [];
        $reachableMeshIndexes = [];

        while ($pendingNodeIndexes !== []) {
            $nodeIndex = array_pop($pendingNodeIndexes);
            if (! is_int($nodeIndex) || ! is_array($nodes[$nodeIndex] ?? null)) {
                return 'The GLB scene references an invalid node.';
            }
            if (isset($visitedNodeIndexes[$nodeIndex])) {
                continue;
            }

            $visitedNodeIndexes[$nodeIndex] = true;
            $node = $nodes[$nodeIndex];

            if (array_key_exists('mesh', $node)) {
                $meshIndex = $node['mesh'];
                if (! is_int($meshIndex) || ! is_array($meshes[$meshIndex] ?? null)) {
                    return 'The GLB scene references an invalid mesh.';
                }
                $reachableMeshIndexes[$meshIndex] = true;
            }

            $children = $node['children'] ?? [];
            if (! is_array($children)) {
                return 'The GLB contains an invalid node hierarchy.';
            }
            foreach ($children as $childIndex) {
                $pendingNodeIndexes[] = $childIndex;
            }
        }

        if ($reachableMeshIndexes === []) {
            return 'The GLB default scene does not contain visible mesh geometry.';
        }

        $minimumY = INF;
        $maximumY = -INF;
        $positionCount = 0;
        $triangleCount = 0;

        foreach (array_keys($reachableMeshIndexes) as $meshIndex) {
            $primitives = $meshes[$meshIndex]['primitives'] ?? null;
            if (! is_array($primitives) || $primitives === []) {
                return 'The GLB contains a mesh without renderable primitives.';
            }

            foreach ($primitives as $primitive) {
                if (! is_array($primitive) || ! is_array($primitive['attributes'] ?? null)) {
                    return 'The GLB contains an invalid mesh primitive.';
                }

                $positionAccessorIndex = $primitive['attributes']['POSITION'] ?? null;
                if (! is_int($positionAccessorIndex) ||
                    ! is_array($accessors[$positionAccessorIndex] ?? null)) {
                    return 'Every GLB mesh primitive must contain a valid POSITION attribute.';
                }

                $accessor = $accessors[$positionAccessorIndex];
                if (($accessor['componentType'] ?? null) !== 5126 ||
                    ($accessor['type'] ?? null) !== 'VEC3' ||
                    ! is_int($accessor['count'] ?? null) ||
                    $accessor['count'] <= 0) {
                    return 'The GLB POSITION data must contain floating-point VEC3 vertices.';
                }

                $minimum = $accessor['min'] ?? null;
                $maximum = $accessor['max'] ?? null;
                if (! is_array($minimum) || count($minimum) !== 3 ||
                    ! is_array($maximum) || count($maximum) !== 3) {
                    return 'The GLB POSITION accessor must include three-dimensional min/max bounds.';
                }

                for ($axis = 0; $axis < 3; $axis++) {
                    if ((! is_int($minimum[$axis]) && ! is_float($minimum[$axis])) ||
                        (! is_int($maximum[$axis]) && ! is_float($maximum[$axis]))) {
                        return 'The GLB contains non-numeric model bounds.';
                    }

                    $minimumValue = (float) $minimum[$axis];
                    $maximumValue = (float) $maximum[$axis];
                    if (! is_finite($minimumValue) || ! is_finite($maximumValue) ||
                        $minimumValue > $maximumValue) {
                        return 'The GLB contains invalid model bounds.';
                    }
                }

                $minimumY = min($minimumY, (float) $minimum[1]);
                $maximumY = max($maximumY, (float) $maximum[1]);
                $positionCount += $accessor['count'];

                $triangleInputCount = $accessor['count'];
                if (array_key_exists('indices', $primitive)) {
                    $indicesAccessorIndex = $primitive['indices'];
                    if (! is_int($indicesAccessorIndex) ||
                        ! is_array($accessors[$indicesAccessorIndex] ?? null) ||
                        ! is_int($accessors[$indicesAccessorIndex]['count'] ?? null) ||
                        $accessors[$indicesAccessorIndex]['count'] <= 0) {
                        return 'The GLB mesh indices must reference a valid accessor count.';
                    }

                    $triangleInputCount = $accessors[$indicesAccessorIndex]['count'];
                }

                $primitiveMode = $primitive['mode'] ?? 4;
                if (! is_int($primitiveMode) || $primitiveMode < 0 || $primitiveMode > 6) {
                    return 'The GLB mesh primitive mode is invalid.';
                }

                $triangleCount += match ($primitiveMode) {
                    4 => intdiv($triangleInputCount, 3),
                    5, 6 => max(0, $triangleInputCount - 2),
                    default => 0,
                };
            }
        }

        if ($positionCount === 0 ||
            ($maximumY - $minimumY) <= self::MIN_HEIGHT_UNITS) {
            return 'The GLB has no measurable height. Export it with Y as the up axis.';
        }

        if ($triangleCount > self::TRIANGLE_HARD_LIMIT) {
            return "The GLB contains {$triangleCount} triangles, above the hard limit of ".self::TRIANGLE_HARD_LIMIT.'. Reduce the mesh before uploading.';
        }

        if ($triangleCount > self::TRIANGLE_WARN_LIMIT) {
            $warnings[] = "The GLB contains {$triangleCount} triangles; the recommended budget is ".self::TRIANGLE_WARN_LIMIT.'.';
        }

        return null;
    }
}
