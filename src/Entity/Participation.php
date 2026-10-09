<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

// L’entité Participation représente la participation d’un utilisateur à une rencontre. Elle contient des informations sur le statut de la participation (en attente, acceptée ou refusée), la date de la demande, l’utilisateur et la rencontre associée.
#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
// Cette contrainte porte sur le couple utilisateur–rencontre : un joueur peut participer à plusieurs rencontres, mais ne peut posséder qu’une seule participation pour chacune.
#[ORM\UniqueConstraint(name: 'UNIQ_PARTICIPATION_UTILISATEUR_RENCONTRE', fields: ['utilisateur', 'rencontre'])]
class Participation
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTEE = 'acceptee';
    public const STATUT_REFUSEE = 'refusee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateDemande;

    // La relation ManyToOne indique que plusieurs participations peuvent être associées à un même utilisateur. 
    // L’attribut inversedBy indique le nom de la propriété correspondante dans l’entité User. 
    // JoinColumn(nullable: false) indique que la colonne de jointure ne peut pas être nulle, donc chaque participation doit avoir un utilisateur.
    #[ORM\ManyToOne(inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    // La relation ManyToOne indique que plusieurs participations peuvent être associées à une même rencontre.
    // L’attribut inversedBy indique le nom de la propriété correspondante dans l’entité Rencontre. 
    // JoinColumn(nullable: false) indique que la colonne de jointure ne peut pas être nulle, donc chaque participation doit avoir une rencontre.
    #[ORM\ManyToOne(inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rencontre $rencontre = null;

    public function __construct()
    {
        $this->dateDemande = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!in_array($statut, [self::STATUT_EN_ATTENTE, self::STATUT_ACCEPTEE, self::STATUT_REFUSEE], true)) {
            throw new \InvalidArgumentException('Statut de participation invalide.');
        }

        $this->statut = $statut;

        return $this;
    }

    public function getDateDemande(): \DateTimeImmutable
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeImmutable $dateDemande): static
    {
        $this->dateDemande = $dateDemande;

        return $this;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getRencontre(): ?Rencontre
    {
        return $this->rencontre;
    }

    public function setRencontre(?Rencontre $rencontre): static
    {
        $this->rencontre = $rencontre;

        return $this;
    }
}
