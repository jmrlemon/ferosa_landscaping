<?php

namespace App\Support;

/**
 * Mobile-first limits for one placeable AR asset.
 *
 * These values are shared by the upload boundary, structural validator, admin
 * guidance and tests so an asset cannot be described as safe in one layer and
 * rejected (or crash a phone) in another.
 */
final class ArAssetBudgets
{
    public const RECOMMENDED_FILE_BYTES = 8 * 1024 * 1024;

    public const MAX_FILE_BYTES = 20 * 1024 * 1024;

    public const MAX_FILE_KILOBYTES = 20 * 1024;

    public const RECOMMENDED_TRIANGLES = 100_000;

    public const MAX_TRIANGLES = 250_000;

    public const RECOMMENDED_TEXTURE_EDGE = 1024;

    public const MAX_TEXTURE_EDGE = 2048;

    public const RECOMMENDED_DECODED_TEXTURE_BYTES = 24 * 1024 * 1024;

    // Three 2048px RGBA PBR maps (including mipmaps) use 64 MiB. This is the
    // standard Meshy mobile export, so allow it while still warning above the
    // much safer 24 MiB target and rejecting a fourth full-size map.
    public const MAX_DECODED_TEXTURE_BYTES = 64 * 1024 * 1024;

    private function __construct() {}
}
