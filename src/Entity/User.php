<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use  ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/users',
            security: "is_granted('ROLE_ADMIN')",
            normalizationContext: ['groups' => ['user:admin-read']]
        ),
        new Get(
            uriTemplate: '/users/{id}',
            security: "is_granted('ROLE_ADMIN')",
            normalizationContext: ['groups' => ['article:read', 'article:details', 'category:read', 'user:read']]
        ),
        new Post(
            uriTemplate: '/users',
            security: "is_granted('ROLE_ADMIN')",
            processor: \App\State\UserProcessor::class,
            denormalizationContext: ['groups' => ['user:write']],
            normalizationContext: ['groups' => ['user:read']]
        ),
        new Patch(
            uriTemplate: '/users/{id}',
            security: "is_granted('ROLE_ADMIN')",
            processor: \App\State\UserProcessor::class,
            denormalizationContext: ['groups' => ['user:write']],
            normalizationContext: ['groups' => ['user:read']]
        ),
        new Delete(
            uriTemplate: '/users/{id}',
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: "You can only delete if you have admin privileges."
        ),
    ]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['article:read', 'article:details', 'user:read', 'user:admin-read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['article:read', 'article:details', 'user:read', 'user:write', 'user:admin-read'])]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups(['user:admin-read', 'user:write'])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['user:write'])]
    private ?string $password = null;

    #[ORM\Column(nullable: true)]
     #[Groups(['user:write'])]
    private ?string $googleId = null;

    #[ORM\Column(nullable: true)]
     #[Groups(['user:write'])]
    private ?string $facebookId = null;

    #[ORM\Column(length: 255, nullable: true)]
   #[Groups(['article:read', 'article:details', 'user:read', 'user:write', 'user:admin-read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['article:read', 'article:details', 'user:read', 'user:write', 'user:admin-read'])]
    private ?string $avatar = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['user:admin-read'])]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $isEmailVerified = false;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'author')]
    private Collection $articles;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->generateEmailVerificationToken();
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getGoogleId(): ?string
    { 
        return $this->googleId; 
    }
    public function setGoogleId(?string $id): self 
    { 
        $this->googleId = $id; return $this; 
    }

    public function getFacebookId(): ?string 
    { 
        return $this->facebookId; 
    }
    public function setFacebookId(?string $id): self 
    { 
        $this->facebookId = $id; return $this; 
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): self
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        if ($emailVerifiedAt) {
            $this->isEmailVerified = true;
        }
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): self
    {
        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): self
    {
        $this->passwordResetToken = $passwordResetToken;
        return $this;
    }

    public function getPasswordResetExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetExpiresAt;
    }

    public function setPasswordResetExpiresAt(?\DateTimeImmutable $passwordResetExpiresAt): self
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): self
    {
        $this->isEmailVerified = $isEmailVerified;
        return $this;
    }

    public function generateEmailVerificationToken(): void
    {
        $this->emailVerificationToken = bin2hex(random_bytes(32));
    }

    public function generatePasswordResetToken(): void
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetExpiresAt = new \DateTimeImmutable('+1 hour');
    }

    public function clearPasswordResetToken(): void
    {
        $this->passwordResetToken = null;
        $this->passwordResetExpiresAt = null;
    }

    public function isPasswordResetTokenValid(): bool
    {
        return $this->passwordResetToken !== null 
            && $this->passwordResetExpiresAt !== null
            && $this->passwordResetExpiresAt > new \DateTimeImmutable();
    }

    public function markEmailAsVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->isEmailVerified = true;
        $this->emailVerificationToken = null;
    }

    public function updateLastLogin(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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
            $article->setAuthor($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            // Note: We don't set author to null as articles must always have an author
            // If you need to transfer articles to another user, use setAuthor() explicitly
        }

        return $this;
    }
}
