<?php

namespace App\Services;

use JsonException;
use RuntimeException;

class PhilippineAddressService
{
    public const SERVICE_AREA_CODE = '0300800000';

    /** @var array{version: string, source: string, areas: array<int, array{code: string, name: string}>, localities: array<string, array<int, array{code: string, name: string}>>, barangays: array<string, array<int, array{code: string, name: string}>>}|null */
    private ?array $data = null;

    /** @return array<int, array{code: string, name: string}> */
    public function areas(): array
    {
        return $this->data()['areas'];
    }

    /** @return array{code: string, name: string}|null */
    public function area(string $code): ?array
    {
        return $this->find($this->areas(), $code);
    }

    /** @return array{code: string, name: string} */
    public function serviceArea(): array
    {
        return $this->area(self::SERVICE_AREA_CODE)
            ?? throw new RuntimeException('The configured Philippine service area could not be loaded.');
    }

    /** @return array<int, array{code: string, name: string}>|null */
    public function localitiesFor(string $areaCode): ?array
    {
        if ($this->area($areaCode) === null) {
            return null;
        }

        return $this->data()['localities'][$areaCode] ?? [];
    }

    /** @return array{code: string, name: string}|null */
    public function locality(string $areaCode, string $localityCode): ?array
    {
        $localities = $this->localitiesFor($areaCode);

        return $localities === null ? null : $this->find($localities, $localityCode);
    }

    /** @return array<int, array{code: string, name: string}>|null */
    public function barangaysFor(string $localityCode): ?array
    {
        return $this->data()['barangays'][$localityCode] ?? null;
    }

    /** @return array{code: string, name: string}|null */
    public function barangay(string $localityCode, string $barangayCode): ?array
    {
        $barangays = $this->barangaysFor($localityCode);

        return $barangays === null ? null : $this->find($barangays, $barangayCode);
    }

    /**
     * @param  array<int, array{code: string, name: string}>  $options
     * @return array{code: string, name: string}|null
     */
    private function find(array $options, string $code): ?array
    {
        foreach ($options as $option) {
            if (hash_equals($option['code'], $code)) {
                return $option;
            }
        }

        return null;
    }

    /** @return array{version: string, source: string, areas: array<int, array{code: string, name: string}>, localities: array<string, array<int, array{code: string, name: string}>>, barangays: array<string, array<int, array{code: string, name: string}>>} */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = resource_path('data/philippine-addresses.json');
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The Philippine address reference data could not be loaded.');
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The Philippine address reference data is invalid.', previous: $exception);
        }

        if (! is_array($data)
            || ! is_string($data['version'] ?? null)
            || ! is_string($data['source'] ?? null)
            || ! is_array($data['areas'] ?? null)
            || ! is_array($data['localities'] ?? null)
            || ! is_array($data['barangays'] ?? null)) {
            throw new RuntimeException('The Philippine address reference data has an unexpected structure.');
        }

        /** @var array{version: string, source: string, areas: array<int, array{code: string, name: string}>, localities: array<string, array<int, array{code: string, name: string}>>, barangays: array<string, array<int, array{code: string, name: string}>>} $data */
        return $this->data = $data;
    }
}
