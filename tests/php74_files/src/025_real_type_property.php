<?php
namespace App\Data\Entities\Product;

class ProductEntity
{
    private ?string $domainExtension = null;

    /**
     * Set invalid product domain extension.
     *
     * @param array $domainExtension Product domain extension.
     */
    public function setInvalidDomainExtension(array $domainExtension): void
    {
        $this->domainExtension = $domainExtension;
    }

    /**
     * Set product domain extension.
     *
     * @param string|null $domainExtension Product domain extension.
     */
    public function setDomainExtension(?string $domainExtension): void
    {
        $this->domainExtension = $domainExtension;
    }

    /**
     * Set invalid product domain extension.
     *
     * @param ?object $domainExtension Product domain extension.
     */
    public function setInvalidDomainExtension2(?object $domainExtension): void
    {
        $this->domainExtension = $domainExtension;
    }
}
