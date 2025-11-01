<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\ArticleSlugUriVariableTransformer;
use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\SluggableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use App\Enum\ArticleStatus;
use App\Validator\CommentMustHaveParent;
use App\Validator\AnswerMustHaveQuestionParent;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[CommentMustHaveParent]
#[AnswerMustHaveQuestionParent]
#[ApiFilter(SearchFilter::class,
    properties: ['category.name' => 'ipartial', 'category.id' => 'exact']
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/articles',
            security: "true",
            normalizationContext: ['groups' => ['article:read']]
        ),
        new Get(
            uriTemplate: '/articles/{slug}',
            uriVariables: [
                'slug' => new Link(fromClass: Article::class, identifiers: ['slug'], parameterName: 'slug')
            ],
            security: "true",
            normalizationContext: ['groups' => ['article:read', 'article:details']]
        ),
        new Post(
            uriTemplate: '/articles',
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            securityMessage: "You must be authenticated to create articles.",
            denormalizationContext: ['groups' => ['article:write']],
            normalizationContext: ['groups' => ['article:read', 'article:details']]
        ),
        new Patch(
            uriTemplate: '/articles/{slug}',
            uriVariables: [
                'slug' => new Link(fromClass: Article::class, identifiers: ['slug'], parameterName: 'slug')
            ],
            security: "is_granted('ARTICLE_EDIT', object)",
            securityMessage: "You can only edit articles you created or if you have moderator privileges.",
            denormalizationContext: ['groups' => ['article:write']],
            normalizationContext: ['groups' => ['article:read', 'article:details']]
        ),
        new Delete(
            uriTemplate: '/articles/{slug}',
            uriVariables: [
                'slug' => new Link(fromClass: Article::class, identifiers: ['slug'], parameterName: 'slug')
            ],
            security: "is_granted('ARTICLE_DELETE', object)",
            securityMessage: "You can only delete articles you created or if you have moderator privileges."
        )
    ]
)]
class Article
{
    use TimestampableTrait;
    use BlameableTrait;
    use SluggableTrait;
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ApiProperty(identifier: false)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $content = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'string', enumType: ArticleStatus::class, length: 25)]
    #[Groups(['article:read', 'article:write'])]
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
    #[Groups(['article:read', 'article:write'])]
    private ?ArticleType $articleType = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['article:read', 'article:details'])]
    private User $author;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'articles', cascade: ['persist'])]
    #[Groups(['article:read', 'article:write'])]
    private Collection $category;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $rootArticle = null;

    #[ORM\Column(nullable: true)]
    private ?int $childrenCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $descendantCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $level = null;

    public function __construct()
    {
        $this->childArticles = new ArrayCollection();
        $this->category = new ArrayCollection();
    }

    public function getId(): ?Uuid
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
        // Note: Hierarchy management (level, rootArticle, counters) is handled 
        // automatically by EntityLifecycleSubscriber
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
        return $this->articleType;
    }

    public function setArticleType(ArticleType $articleType): static
    {
        $this->articleType = $articleType;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategory(): Collection
    {
        return $this->category;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->category->contains($category)) {
            $this->category->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->category->removeElement($category);

        return $this;
    }

    public function getRootArticle(): ?self
    {
        return $this->rootArticle;
    }

    public function setRootArticle(?self $rootArticle): static
    {
        $this->rootArticle = $rootArticle;

        return $this;
    }

    public function getChildrenCount(): ?int
    {
        return $this->childrenCount;
    }

    public function setChildrenCount(?int $childrenCount): static
    {
        $this->childrenCount = $childrenCount;

        return $this;
    }

    public function getDescendantCount(): ?int
    {
        return $this->descendantCount;
    }

    public function setDescendantCount(?int $descendantCount): static
    {
        $this->descendantCount = $descendantCount;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): static
    {
        $this->level = $level;
        // Note: Child level updates are handled by EntityLifecycleSubscriber
        return $this;
    }


}
