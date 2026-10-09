<?php

namespace App\Entity;

use App\Repository\RencontreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RencontreRepository::class)]
class Rencontre
{
    // Les attributs ORM décrivent le stockage. 
    // Les attributs Assert définissent les règles vérifiées par le validateur Symfony, notamment lorsque nous appellerons $form->isValid().


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Veuillez renseigner un titre.')]
    #[Assert\Length(
        max: 120,
        maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $titre = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Veuillez renseigner une ville.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La ville ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $ville = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez préciser le lieu.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le lieu ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $lieu = null;

    // datetime_immutable représente une date et une heure avec un objet PHP DateTimeImmutable. Modifier cette date produit un nouvel objet.
    #[ORM\Column]
    #[Assert\NotNull(message: 'Veuillez renseigner la date et l’heure.')]
    // Assert\GreaterThan vérifie que la date est supérieure à la date actuelle (now).
    #[Assert\GreaterThan(
        value: 'now',
        message: 'La rencontre doit avoir lieu dans le futur.'
    )]
    private ?\DateTimeImmutable $dateHeure = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Veuillez indiquer le nombre de places recherchées.')]
    #[Assert\Positive(message: 'Le nombre de places doit être supérieur à zéro.')]
    private ?int $placesRecherchees = null;

    // Le type TEXT est utilisé pour les textes longs. La propriété description peut être nulle.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // inversedBy: 'rencontresOrganisees' : indique le nom de la propriété correspondante dans User
    // JoinColumn(nullable: false) : indique que la colonne de jointure ne peut pas être nulle, donc chaque rencontre doit avoir un organisateur.
    #[ORM\ManyToOne(inversedBy: 'rencontresOrganisees')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $organisateur = null;

    /**
     * @var Collection<int, Participation>
     */

    // targetEntity: Participation::class : indique que la relation porte sur l’entité Participation
    // mappedBy: 'rencontre' : indique que la relation est mappée par la propriété rencontre de l’entité Participation. C’est donc la propriété rencontre qui est propriétaire de la relation.
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'rencontre')]
    private Collection $participations;

    // La propriété annulee est un booléen qui indique si la rencontre est annulée ou non. La valeur par défaut est false.
    #[ORM\Column(options: ['default' => false])]
    private bool $annulee = false;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getDateHeure(): ?\DateTimeImmutable
    {
        return $this->dateHeure;
    }

    public function setDateHeure(\DateTimeImmutable $dateHeure): static
    {
        $this->dateHeure = $dateHeure;

        return $this;
    }

    public function getPlacesRecherchees(): ?int
    {
        return $this->placesRecherchees;
    }

    public function setPlacesRecherchees(int $placesRecherchees): static
    {
        $this->placesRecherchees = $placesRecherchees;

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

    public function getOrganisateur(): ?User
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?User $organisateur): static
    {
        $this->organisateur = $organisateur;

        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setRencontre($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            // set the owning side to null (unless already changed)
            if ($participation->getRencontre() === $this) {
                $participation->setRencontre(null);
            }
        }

        return $this;
    }

    public function getNombreParticipationsAcceptees(): int
    {
        $nombre = 0;

        foreach ($this->participations as $participation) {
            if ($participation->getStatut() === Participation::STATUT_ACCEPTEE) {
                $nombre++;
            }
        }

        return $nombre;
    }

    public function getPlacesRestantes(): int
    {
        return $this->placesRecherchees - $this->getNombreParticipationsAcceptees();
    }

    public function isAnnulee(): bool
    {
        return $this->annulee;
    }

    public function annuler(): void
    {
        $this->annulee = true;
    }
}
