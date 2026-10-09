<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

// Cet attribut indique que Doctrine doit associer cette classe à une table de la base.
#[ORM\Entity(repositoryClass: UserRepository::class)]
// Cet attribut indique que la colonne email doit être unique dans la table.
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
// UserInterface permet à Symfony d’obtenir l’identifiant de connexion et les rôles de l’utilisateur.
// PasswordAuthenticatedUserInterface indique que l’utilisateur possède un mot de passe accessible par getPassword()
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Id : cette propriété est la clé primaire.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    // #[ORM\Column] indique que cette propriété est mappée à une colonne de la table. Le type de la colonne est automatiquement déduit du type de la propriété.
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Veuillez renseigner une adresse e-mail.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L’adresse e-mail ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    private ?string $pseudo = null;

    /**
     * @var Collection<int, Rencontre>
     */

    // targetEntity: Rencontre::class : indique que la relation porte sur l’entité Rencontre
    // mappedBy: 'organisateur' : indique que la relation est mappée par la propriété organisateur de l’entité Rencontre. C’est donc la propriété organisateur qui est propriétaire de la relation.
    #[ORM\OneToMany(targetEntity: Rencontre::class, mappedBy: 'organisateur')]
    private Collection $rencontresOrganisees;

    /**
     * @var Collection<int, Participation>
     */

    // targetEntity: Participation::class : indique que la relation porte sur l’entité Participation
    // mappedBy: 'utilisateur' : indique que la relation est mappée par la propriété utilisateur de l’entité Participation. C’est donc la propriété utilisateur qui est propriétaire de la
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'utilisateur')]
    private Collection $participations;

    public function __construct()
    {
        $this->rencontresOrganisees = new ArrayCollection();
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // ?string autorise null dans l’objet PHP, mais ne rend pas automatiquement la colonne nullable en base. Pour cela, il faudrait #[ORM\Column(nullable: true)].
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
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    /**
     * @return Collection<int, Rencontre>
     */
    public function getRencontresOrganisees(): Collection
    {
        return $this->rencontresOrganisees;
    }

    // La méthode d’ajout met à jour les deux côtés en mémoire
    public function addRencontresOrganisee(Rencontre $rencontresOrganisee): static
    {
        // Vérifie si la rencontre n’est pas déjà dans la collection
        if (!$this->rencontresOrganisees->contains($rencontresOrganisee)) {
            $this->rencontresOrganisees->add($rencontresOrganisee);
            $rencontresOrganisee->setOrganisateur($this);
        }

        return $this;
    }

    public function removeRencontresOrganisee(Rencontre $rencontresOrganisee): static
    {
        // Vérifie si la rencontre est dans la collection et la supprime
        if ($this->rencontresOrganisees->removeElement($rencontresOrganisee)) {
            // Met à jour le côté inverse de la relation si nécessaire
            if ($rencontresOrganisee->getOrganisateur() === $this) {
                $rencontresOrganisee->setOrganisateur(null);
            }
        }
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
            $participation->setUtilisateur($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getUtilisateur() === $this) {
                $participation->setUtilisateur(null);
            }
        }

        return $this;
    }
}
