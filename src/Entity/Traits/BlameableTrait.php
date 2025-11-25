<?php

namespace App\Entity\Traits;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait BlameableTrait
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['article:read', 'article:details'])]
    private User $author;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['article:details'])]
    private ?User $updatedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['article:read', 'article:details'])]
    private ?User $publishedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['article:read', 'article:details'])]
    private ?User $archivedBy = null;

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getPublishedBy(): ?User
    {
        return $this->publishedBy;
    }

    public function setPublishedBy(?User $publishedBy): static
    {
        $this->publishedBy = $publishedBy;

        return $this;
    }

    public function getArchivedBy(): ?User
    {
        return $this->archivedBy;
    }

    public function setArchivedBy(?User $archivedBy): static
    {
        $this->archivedBy = $archivedBy;

        return $this;
    }
}