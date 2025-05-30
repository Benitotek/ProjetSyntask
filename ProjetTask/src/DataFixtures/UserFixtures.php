// src/DataFixtures/UserFixtures.php
namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        // Création de 10 utilisateurs
        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $user->setUsername('User' . $i);
            $user->setPassword('password' . $i);
            $user->setEmail('user' . $i . '@example.com');

            $manager->persist($user);
        }

        $manager->flush();
    }
}
