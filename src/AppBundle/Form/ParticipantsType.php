<?php
/**
 * This file contains only the ParticipantsType class.
 */

declare(strict_types=1);

namespace AppBundle\Form;

use AppBundle\Model\Event;
use AppBundle\Model\Participant;
use AppBundle\Repository\EventRepository;
use AppBundle\Repository\ParticipantRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * A ParticipantsType is the type of Form used to enter participants of an Event.
 */
class ParticipantsType extends AbstractType
{
    /** @var EventRepository Repository for the Event model. */
    private $eventRepo;

    /** @var ParticipantRepository Repository for the Participant model. */
    private $participantRepo;

    /**
     * ParticipantsType constructor.
     * @param EventRepository $eventRepo
     * @param ParticipantRepository $participantRepo
     */
    public function __construct(EventRepository $eventRepo, ParticipantRepository $participantRepo)
    {
        $this->eventRepo = $eventRepo;
        $this->participantRepo = $participantRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('participants', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'required' => false,
                'constraints' => [new Valid()],
            ])
            ->add('new_participants', TextareaType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('submit', SubmitType::class)
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onParticipantPreSubmit']);

        $builder->get('participants')
            ->addModelTransformer($this->getParticipantCallbackTransformer($options['event']));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'participantForm';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'event' => null,
        ]);
    }

    /**
     * Format data before the participant form is submitted.
     * @param FormEvent $formEvent
     */
    public function onParticipantPreSubmit(FormEvent $formEvent)
    {
        $event = $formEvent->getData();

        /**
         * Parse new usernames from the textarea, removing carriage returns and extraneous spacing.
         * @var string[]
         */
        $newParUsernames = explode("\n", $event['new_participants']);

        $participants = isset($event['participants']) ? $event['participants'] : [];

        // Combine usernames from inputs and textarea.
        $event['participants'] = array_merge($participants, $newParUsernames);

        // Now normalize all the usernames.
        // TODO: Refactor this out, doing the same for Organizers to a Program.
        // Need to somehow hook into a callback in the model layer before validations are ran.
        $event['participants'] = array_map(function ($username) {
            $normalized = trim(str_replace('_', ' ', str_replace("\r", '', $username)));

            // Same as ucfirst but works on all locale settings. This is what MediaWiki wants.
            return mb_strtoupper(mb_substr($normalized, 0, 1)).mb_substr($normalized, 1);
        }, $event['participants']);

        // Remove duplicates and blank entries.
        $event['participants'] = array_filter(array_unique($event['participants']));

        // Sort alphabetically.
        sort($event['participants']);

        // Now unset new_participants so they aren't duplicated in the returned form.
        unset($event['new_participants']);

        $formEvent->setData($event);
    }

    /**
     * Transform participant data to or from the form. This essentially pulls in the username from the user ID,
     * and sets the user ID before persisting so that the username can be validated.
     * @param Event $event
     * @return CallbackTransformer
     */
    private function getParticipantCallbackTransformer(Event $event)
    {
        return new CallbackTransformer(
            // Transform to the form.
            function (Collection $participantObjects) {
                $parIds = array_map(function (Participant $participant) {
                    return $participant->getUserId();
                }, $participantObjects->toArray());

                $usernames = array_column($this->eventRepo->getUsernamesFromIds($parIds), 'user_name');
                sort($usernames);
                return $usernames;
            },
            // Transform from the form.
            function (array $participantNames) use ($event) {
                // Get the rows for each requested username.
                $rows = $this->participantRepo->getRowsFromUsernames($participantNames);

                // Should be in alphabetical order, the same order we'll show it
                // to the user in the returned form.
                usort($rows, function ($a, $b) {
                    return strnatcmp($a['user_name'], $b['user_name']);
                });

                $participants = [];

                // Create or get Participants from the usernames.
                foreach ($rows as $row) {
                    $participant = $this->getParticipantFromRow($event, $row);
                    $event->addParticipant($participant);
                    $participants[] = $participant;
                }

                return $participants;
            }
        );
    }

    /**
     * Given a row from ParticipantRepository::getRowsFromUsernames(), find or instantiate a new Participant.
     * @param Event $event
     * @param array $row As fetched from ParticipantRepository::getRowsFromUsernames().
     * @return Participant
     */
    private function getParticipantFromRow(Event $event, array $row)
    {
        if ($row['user_id'] === null) {
            // Username is invalid, so just return a new Participant
            // without a user ID so that the form can produce errors.
            $participant = new Participant($event);
        } else {
            // Otherwise we need find the one that exists in grantmetrics.
            $participant = $this->participantRepo->findOneBy([
                'userId' => $row['user_id'],
                'event' => $event,
            ]);

            if ($participant === null) {
                // Participant doesn't exist in grantmetrics yet,
                // so we'll create a new, blank Participant.
                $participant = new Participant($event);
                $participant->setUserId($row['user_id']);
            }
        }

        $participant->setUsername($row['user_name']);

        return $participant;
    }
}
