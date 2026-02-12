<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;

#[Hateoas\Relation(
    "self",
    href: new Hateoas\Route(
        "detail_book",
        parameters: ["id" => "expr(object.getId())"],
        absolute: true
    ),
    exclusion: new Hateoas\Exclusion(groups: ["book:view"])
),
Hateoas\Relation(
    "delete",
    href: new Hateoas\Route(
        "delete_book",
        parameters: ["id" => "expr(object.getId())"],
        absolute: true
    ),
    exclusion: new Hateoas\Exclusion(groups: ["book:view"], excludeIf:"expr(not is_granted('ROLE_ADMIN'))")
),
Hateoas\Relation(
    "update",
    href: new Hateoas\Route(
        "update_book",
        parameters: ["id" => "expr(object.getId())"],
        absolute: true
    ),
    exclusion: new Hateoas\Exclusion(groups: ["book:view"])
),
Hateoas\Relation(
    "all",
    href: new Hateoas\Route(
        "all_books",
        absolute: true
    ),
    exclusion: new Hateoas\Exclusion(groups: ["book:view"])
),
Hateoas\Relation(
    "create",
    href: new Hateoas\Route(
        "create_book",
        absolute: true
    ),
    exclusion: new Hateoas\Exclusion(groups: ["book:view"], excludeIf:"expr(not is_granted('ROLE_ADMIN'))")
)]
#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["book:view"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["book:view","book:create","book:update"])]
    #[Assert\NotBlank(message: "Le titre du livre est obligatoire.")]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: "Le titre du livre doit comporter au moins {{ limit }} caractère.",
        maxMessage: "Le titre du livre ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["book:view","book:create","book:update"])]
    private ?string $coverText = null;

    #[ORM\ManyToOne(inversedBy: 'books', cascade: ['persist'] )]
    #[Groups(["book:view","book:create"])]
    private ?Author $author = null;

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

    public function getCoverText(): ?string
    {
        return $this->coverText;
    }

    public function setCoverText(?string $coverText): static
    {
        $this->coverText = $coverText;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): static
    {
        $this->author = $author;

        return $this;
    }
}
