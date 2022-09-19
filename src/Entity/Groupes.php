<?php

namespace App\Entity;

use App\Repository\GroupesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GroupesRepository::class)
 */
class Groupes
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $dn;

    /**
     * @ORM\ManyToMany(targetEntity=Utilisateurs::class, inversedBy="groupes")
     */
    private $membres;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDn(): ?string
    {
        return $this->dn;
    }

    public function setDn(string $dn): self
    {
        $this->dn = $dn;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateurs>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(Utilisateurs $membre): self
    {
        if (!$this->membres->contains($membre)) {
            $this->membres[] = $membre;
        }

        return $this;
    }

    public function removeMembre(Utilisateurs $membre): self
    {
        $this->membres->removeElement($membre);

        return $this;
    }
}
