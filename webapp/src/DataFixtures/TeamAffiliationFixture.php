<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TeamAffiliation;
use Doctrine\Persistence\ObjectManager;

/**
 * Class TeamAffiliationFixture
 * @package App\DataFixtures
 */
class TeamAffiliationFixture extends AbstractExampleDataFixture
{
    public const AFFILIATION_REFERENCE = 'affiliation';

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $affiliation = new TeamAffiliation();
        $affiliation
            ->setExternalid('BUAA')
            ->setShortname('BUAA')
            ->setName('北京航空航天大学')
            ->setCountry('CHN');

        $manager->persist($affiliation);
        $manager->flush();

        $this->addReference(self::AFFILIATION_REFERENCE, $affiliation);
    }
}
