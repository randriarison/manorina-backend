<?php

namespace App\Entity\Traits;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait SluggableTrait
{
    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['article:read'])]
    #[ApiProperty(identifier: true)]
    private ?string $slug = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Set slug from title if not already set
     * This will be called by the EntityLifecycleSubscriber with proper slugger
     */
    public function updateSlug(string $title, string $generatedSlug): void
    {
        if (empty($this->slug)) {
            $this->slug = $generatedSlug;
        }
    }

    /**
     * Force update slug (useful for updates when title changes)
     */
    public function forceUpdateSlug(string $generatedSlug): void
    {
        $this->slug = $generatedSlug;
    }
}