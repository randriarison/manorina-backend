<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\SluggableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\UX\Turbo\Attribute\Broadcast;
use App\Enum\ArticleStatus;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ApiResource]
#[Broadcast]
class Article
{
    use TimestampableTrait;
    use BlameableTrait;
    use SluggableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;



    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'string', enumType: ArticleStatus::class, length: 25)]
    private ?ArticleStatus $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contentMg = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contentEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titleMg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titleEn = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $initialLang = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childArticles')]
    private ?self $parentArticle = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentArticle')]
    private Collection $childArticles;

    #[ORM\Column(enumType: ArticleType::class)]
    private ?ArticleType $ArticleType = null;

    public function __construct()
    {
        $this->childArticles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }



    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getStatus(): ?ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): static
    {
        $this->status = $status;

        return $this;
    }



    public function getContentMg(): ?string
    {
        return $this->contentMg;
    }

    public function setContentMg(?string $contentMg): static
    {
        $this->contentMg = $contentMg;

        return $this;
    }

    public function getContentEn(): ?string
    {
        return $this->contentEn;
    }

    public function setContentEn(?string $contentEn): static
    {
        $this->contentEn = $contentEn;

        return $this;
    }

    public function getTitleMg(): ?string
    {
        return $this->titleMg;
    }

    public function setTitleMg(?string $titleMg): static
    {
        $this->titleMg = $titleMg;

        return $this;
    }

    public function getTitleEn(): ?string
    {
        return $this->titleEn;
    }

    public function setTitleEn(?string $titleEn): static
    {
        $this->titleEn = $titleEn;

        return $this;
    }

    public function getInitialLang(): ?string
    {
        return $this->initialLang;
    }

    public function setInitialLang(?string $initialLang): static
    {
        $this->initialLang = $initialLang;

        return $this;
    }

    public function getParentArticle(): ?self
    {
        return $this->parentArticle;
    }

    public function setParentArticle(?self $parentArticle): static
    {
        $this->parentArticle = $parentArticle;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildArticles(): Collection
    {
        return $this->childArticles;
    }

    public function addChildArticle(self $childArticle): static
    {
        if (!$this->childArticles->contains($childArticle)) {
            $this->childArticles->add($childArticle);
            $childArticle->setParentArticle($this);
        }

        return $this;
    }

    public function removeChildArticle(self $childArticle): static
    {
        if ($this->childArticles->removeElement($childArticle)) {
            // set the owning side to null (unless already changed)
            if ($childArticle->getParentArticle() === $this) {
                $childArticle->setParentArticle(null);
            }
        }

        return $this;
    }

    public function getArticleType(): ?ArticleType
    {
        return $this->ArticleType;
    }

    public function setArticleType(ArticleType $ArticleType): static
    {
        $this->ArticleType = $ArticleType;

        return $this;
    }


}
