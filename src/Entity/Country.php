<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups('user:read', 'article:read')]
    private ?string $name = null;

    /**
     * @Gedmo\Slug(fields={"name"})
     */
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $code = null;


    #[ORM\ManyToOne(targetEntity: Continent::class, inversedBy: 'countries')]
    #[MaxDepth(1)]
    private ?Continent $continent = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups('user:read')]
    private ?int $telCode ;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $currencyCode = null;

    /**
     * @var Collection<int, Region>
     */
    #[ORM\OneToMany(targetEntity: Region::class, mappedBy: 'country')]
    private Collection $regions;

    /**
     * @var Collection<int, District>
     */
    #[ORM\OneToMany(targetEntity: District::class, mappedBy: 'country')]
    private Collection $districts;

    /**
     * @var Collection<int, Commune>
     */
    #[ORM\OneToMany(targetEntity: Commune::class, mappedBy: 'country')]
    private Collection $communes;

    /**
     * @var Collection<int, Fokontany>
     */
    #[ORM\OneToMany(targetEntity: Fokontany::class, mappedBy: 'country')]
    private Collection $fokontanys;

    public function __construct()
    {
        $this->regions = new ArrayCollection();
        $this->districts = new ArrayCollection();
        $this->communes = new ArrayCollection();
        $this->fokontanys = new ArrayCollection();
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

    public function getSlug() : ?string
    {
        return $this->slug;
    }

    public function getContinent() : ?Continent
    {
        return $this->continent;
    }

    public function setContinent(?Continent $continent) : self
    {
        $this->continent = $continent;
        return $this;
    }

    public function getCode() : ?string
    {
        return $this->code;
    }

    public function setCode(?string $code) : self
    {
        $this->code = $code;
        return $this;
    }

    public function getTelCode() : ?int
    {
        return $this->telCode;
    }
    public function setTelCode(?int $telCode) : self
    {
        $this->telCode = $telCode;
        return $this;
    }
    public function getCurrency() : ?string
    {
        return $this->currency;
    }
    public function setCurrency(?string $currency) : self
    {
        $this->currency = $currency;
        return $this;
    }
    public function getCurrencyCode() : ?string
    {
        return $this->currencyCode;
    }
    public function setCurrencyCode(?string $currencyCode) : self
    {
        $this->currencyCode = $currencyCode;
        return $this;
    }


    /**
     * @return Collection<int, Region>
     */
    public function getRegions(): Collection
    {
        return $this->regions;
    }

    public function addRegion(Region $region): static
    {
        if (!$this->regions->contains($region)) {
            $this->regions->add($region);
            $region->setCountry($this);
        }

        return $this;
    }

    public function removeRegion(Region $region): static
    {
        if ($this->regions->removeElement($region)) {
            // set the owning side to null (unless already changed)
            if ($region->getCountry() === $this) {
                $region->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, District>
     */
    public function getDistricts(): Collection
    {
        return $this->districts;
    }

    public function addDistrict(District $district): static
    {
        if (!$this->districts->contains($district)) {
            $this->districts->add($district);
            $district->setCountry($this);
        }

        return $this;
    }

    public function removeDistrict(District $district): static
    {
        if ($this->districts->removeElement($district)) {
            // set the owning side to null (unless already changed)
            if ($district->getCountry() === $this) {
                $district->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Commune>
     */
    public function getCommunes(): Collection
    {
        return $this->communes;
    }

    public function addCommune(Commune $commune): static
    {
        if (!$this->communes->contains($commune)) {
            $this->communes->add($commune);
            $commune->setCountry($this);
        }

        return $this;
    }

    public function removeCommune(Commune $commune): static
    {
        if ($this->communes->removeElement($commune)) {
            // set the owning side to null (unless already changed)
            if ($commune->getCountry() === $this) {
                $commune->setCountry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Fokontany>
     */
    public function getFokontanys(): Collection
    {
        return $this->fokontanys;
    }

    public function addFokontany(Fokontany $fokontany): static
    {
        if (!$this->fokontanys->contains($fokontany)) {
            $this->fokontanys->add($fokontany);
            $fokontany->setCountry($this);
        }

        return $this;
    }

    public function removeFokontany(Fokontany $fokontany): static
    {
        if ($this->fokontanys->removeElement($fokontany)) {
            // set the owning side to null (unless already changed)
            if ($fokontany->getCountry() === $this) {
                $fokontany->setCountry(null);
            }
        }

        return $this;
    }
}
