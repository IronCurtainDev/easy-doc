<?php

namespace EasyDoc\Contracts;

use Illuminate\Support\Collection;

interface GeneratorInterface
{
    /**
     * Generate the documentation file(s).
     *
     * @param Collection $apiCalls Collection of APICall objects
     * @param string $outputDir Base directory for output
     * @return array List of generated files ['Name' => 'Path']
     */
    public function generate(Collection $apiCalls, string $outputDir): array;
}
