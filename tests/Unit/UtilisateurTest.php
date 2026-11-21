<?php

namespace App\Tests\Unit;

use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class UtilisateurTest extends TestCase
{
    public function testRolesParDefautContientRoleUser(): void
    {
        $user = new Utilisateur();
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testAjoutDeRolesSpecifiques(): void
    {
        $user = new Utilisateur();
        $user->setRoles(['ROLE_OWNER']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_OWNER', $roles);
        $this->assertContains('ROLE_USER', $roles); // ROLE_USER doit toujours être présent
    }

    public function testUserIdentifierCorrespondAEmail(): void
    {
        $user = new Utilisateur();
        $user->setEmail('test@tunisiastay.tn');

        $this->assertEquals('test@tunisiastay.tn', $user->getUserIdentifier());
    }

    public function testNomEtPrenomComplet(): void
    {
        $user = new Utilisateur();
        $user->setNom('Ben Ali');
        $user->setPrenom('Sami');

        $this->assertEquals('Ben Ali', $user->getNom());
        $this->assertEquals('Sami', $user->getPrenom());
    }
}
