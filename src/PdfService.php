<?php

namespace Incapption\PdfService;

class PdfService
{
    /**
     * @var string
     */
    private $hmacSecret;

    public function __construct(string $hmacSecret)
    {
        $this->hmacSecret = $hmacSecret;
    }


}