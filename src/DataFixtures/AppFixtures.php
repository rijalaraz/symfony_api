<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Création d'un utilisateur normal
        $user = new User();
        $user->setEmail('user@bookapi.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $manager->persist($user);

        // Création d'un utilisateur admin
        $admin = new User();
        $admin->setEmail('admin@bookapi.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password'));
        $manager->persist($admin);

        // Création des auteurs.
        $listAuthor = [];
        for ($i=0; $i < 10; $i++) { 
            $author = new Author() ;
            $author->setFirstName("Prénom ". $i) ;
            $author->setLastName("Nom ". $i) ;
            $manager->persist($author);
            // On sauvegarde l'auteur créé dans un tableau.
            $listAuthor[] = $author;
        }


        // Création d'une vingtaine de livres ayant pour titre
        for ($i=0; $i < 20; $i++) { 
            $book = new Book();
            $book->setTitle("Livre " . $i);
            $book->setCoverText("Quatrième de couverture numéro : ". $i);
            // On lie le livre à un auteur pris au hasard dans le tableau des auteurs.
            $book->setAuthor($listAuthor[array_rand($listAuthor)]);

            $manager->persist($book);
        }

        $manager->flush();
    }
}
