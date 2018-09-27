<?php
/**
 * This file contains only the ParticipantTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Event;
use AppBundle\Model\Organizer;
use AppBundle\Model\Participant;
use AppBundle\Model\Program;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the Participant class.
 */
class ParticipantTest extends GrantMetricsTestCase
{
    /**
     * Tests constructor and basic getters.
     */
    public function testConstructor(): void
    {
        [$event, $participant] = $this->createEventAndParticipant();

        // Basic getters.
        static::assertEquals(50, $participant->getUserId());
        static::assertEquals($event, $participant->getEvent());

        // Make sure the association was made on the Event object, too.
        static::assertEquals($participant, $event->getParticipants()[0]);
    }

    /**
     * Basic setters.
     */
    public function testSetters(): void
    {
        [, $participant] = $this->createEventAndParticipant();

        $participant->setUsername('MusikAnimal');
        $participant->setUserId(123);

        static::assertEquals(123, $participant->getUserId());
        static::assertEquals('MusikAnimal', $participant->getUsername());
    }

    /**
     * Create a sample Event and Participant.
     * @return mixed[]
     */
    public function createEventAndParticipant(): array
    {
        $organizer = new Organizer(50);
        $program = new Program($organizer);
        $event = new Event(
            $program,
            '  My program  ',
            '2017-01-01',
            new \DateTime('2017-03-01'),
            'America/New_York'
        );
        $participant = new Participant($event, 50);

        return [$event, $participant];
    }
}
