<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\SluggableTrait;
use App\Entity\Traits\TimestampableTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\NumericFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use App\Validator\CategoryContentValidation;


#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[CategoryContentValidation]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/categories',
            security: "true",
            normalizationContext: ['groups' => ['category:read']]
        ),
        new Get(
            uriTemplate: '/categories/{slug}',
            security: "true",
            normalizationContext: ['groups' => ['category:read']]
        ),
        new Post(
            uriTemplate: '/categories',
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            securityMessage: "You must be authenticated to create categories.",
            denormalizationContext: ['groups' => ['category:write']],
            normalizationContext: ['groups' => ['category:read']]
        ),
        new Patch(
            uriTemplate: '/categories/{slug}',
            security: "is_granted('ROLE_ADMIN') or object.getAuthor() == user",
            securityMessage: "You can only edit categories you created or if you have admin privileges.",
            denormalizationContext: ['groups' => ['category:write']],
            normalizationContext: ['groups' => ['category:read']]
        ),
        new Delete(
            uriTemplate: '/categories/{slug}',
            security: "is_granted('ROLE_ADMIN') or object.getAuthor() == user",
            securityMessage: "You can only delete categories you created or if you have admin privileges."
        )
        ],
        paginationEnabled: false
)]
#[ApiFilter(NumericFilter::class, properties: ['level'])]
#[ApiFilter(SearchFilter::class,
    properties: ['name' => 'ipartial', 'nameMg' => 'ipartial']
)]
#[ApiFilter(OrderFilter::class, properties: [
    'name' => null, 
    'nameMg' => null,  
    'level' => null
], arguments: ['orderParameterName' => 'order'])]
class Category
{
    use TimestampableTrait;
    use BlameableTrait;
    use SluggableTrait;
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ApiProperty(identifier: false)]
    #[Groups(['category:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['category:read', 'category:write', 'article:read'])]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[Groups(['category:read', 'category:write'])]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[Groups(['category:read'])]
    private Collection $children;

    #[ORM\Column]
    #[Groups(['category:read', 'category:write'])]
    private ?int $level = null;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'category')]
    private Collection $articles;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['category:read', 'category:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['category:read', 'category:write', 'article:read'])]
    private ?string $nameMg = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['category:read', 'category:write'])]
    private ?string $descriptionMg = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->addCategory($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            $article->removeCategory($this);
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getNameMg(): ?string
    {
        return $this->nameMg;
    }

    public function setNameMg(?string $nameMg): static
    {
        $this->nameMg = $nameMg;

        return $this;
    }

    public function getDescriptionMg(): ?string
    {
        return $this->descriptionMg;
    }

    public function setDescriptionMg(?string $descriptionMg): static
    {
        $this->descriptionMg = $descriptionMg;

        return $this;
    }
}
