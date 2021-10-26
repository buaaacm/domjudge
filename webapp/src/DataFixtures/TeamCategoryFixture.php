<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

class TeamCategoryFixture extends AbstractExampleDataFixture
{
    public const IN_SCHOOL_REFERENCE = 'in_school';

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $participants = new TeamCategory();
        $participants->setName('Participants');

        $observers = new TeamCategory();
        $observers
            ->setName('Observers')
            ->setSortorder(1)
            ->setColor('#ffcc33');

        $organisation = new TeamCategory();
        $organisation
            ->setName('Organisation')
            ->setSortorder(1)
            ->setColor('#ff99cc')
            ->setVisible(false);

        $inSchool = new TeamCategory();
        $inSchool
            ->setName('校内')
            ->setSortorder(0)
            ->setColor('#ffffff');

        $outSchool = new TeamCategory();
        $outSchool
            ->setName('校外')
            ->setSortorder(0)
            ->setColor('#cccccc');

        $manager->persist($participants);
        $manager->persist($observers);
        $manager->persist($organisation);
        $manager->persist($inSchool);
        $manager->persist($outSchool);
        $manager->flush();

        $this->addReference(self::IN_SCHOOL_REFERENCE, $inSchool);
    }
}
