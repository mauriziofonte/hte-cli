<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Contract for managing self-signed SSL certificates.
 */
interface SslCertManagerInterface
{
    /**
     * Generate a bash script that creates a self-signed SSL certificate.
     *
     * @param string $domain
     * @param int $days Certificate validity in days.
     * @return string The bash script content.
     */
    public function generateScript(string $domain, int $days = 10950);

    /**
     * Get the directory where self-signed certificates are stored.
     *
     * @return string
     */
    public function getCertsDir();
}
