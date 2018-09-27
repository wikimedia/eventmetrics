<?php
/**
 * This file contains only the EventType class.
 */

declare(strict_types=1);

namespace AppBundle\Form;

use AppBundle\Model\Event;
use AppBundle\Model\EventWiki;
use AppBundle\Repository\EventWikiRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * An EventType is the type of form used when creating and updating the main details of an Event.
 */
class EventType extends AbstractType
{
    /** @var EventWikiRepository Repository for the Event model. */
    private $ewRepo;

    /**
     * EventType constructor.
     * @param EventWikiRepository $ewRepo
     */
    public function __construct(EventWikiRepository $ewRepo)
    {
        $this->ewRepo = $ewRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('wikis', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'empty_data' => '',
                'required' => true,
                'constraints' => [new Valid(), new NotBlank()],
                'data' => $options['event']->getOrphanWikisAndFamilies(),
                'data_class' => null,
            ])
            ->add('time', TextType::class, [
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('start', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => false,
                'view_timezone' => 'UTC',
                'constraints' => [new Valid()],
            ])
            ->add('end', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => false,
                'view_timezone' => 'UTC',
                'constraints' => [new Valid()],
            ])
            ->add('timezone', TimezoneType::class, [
                'choices' => $this->getTimezones(),
                'choice_loader' => null,
            ])
            ->add('submit', SubmitType::class)
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onEventPreSubmit']);

        $builder->get('wikis')
            ->addModelTransformer($this->getWikiCallbackTransformer($options['event']));
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
     * Get options for the timezone dropdown, grouping by region and also prefixing each option with the region.
     * @return string[]
     */
    private function getTimezones(): array
    {
        $timezones = [
            'UTC' => 'UTC',
        ];

        foreach (\DateTimeZone::listIdentifiers() as $timezone) {
            $region = str_replace('_', ' ', explode('/', $timezone)[0]);
            $displayTimezone = str_replace('_', ' ', $timezone);

            if ('UTC' === $region) {
                continue;
            }

            if (isset($timezones[$region])) {
                $timezones[$region][$displayTimezone] = $timezone;
            } else {
                $timezones[$region] = [
                    $displayTimezone => $timezone,
                ];
            }
        }

        return $timezones;
    }

    /**
     * Normalize the form data before submitting.
     * @param FormEvent $formEvent
     */
    public function onEventPreSubmit(FormEvent $formEvent): void
    {
        $event = $formEvent->getData();

        // Remove duplicate and blank wikis.
        $event['wikis'] = array_filter(array_values(array_unique($formEvent->getData()['wikis'])));

        $formEvent->setData($event);
    }

    /**
     * Transform wiki data to or from the form.
     * @param Event $event
     * @return CallbackTransformer
     */
    private function getWikiCallbackTransformer(Event $event): CallbackTransformer
    {
        return new CallbackTransformer(
            // Transform to the form.
            function (Collection $wikiObjects) {
                // To domain names for the form, from EventWikis.
                $wikis = $wikiObjects->toArray();
                return array_map(function (EventWiki $wiki) {
                    return $wiki->getDomain();
                }, $wikis);
            },
            // Transform from the form.
            function (array $wikis) use ($event) {
                return array_filter(
                    $this->normalizeEventWikis($wikis, $event)
                );
            }
        );
    }

    /**
     * Take the list of wikis provided by the user (enwiki, en.wikipedia, or en.wikipedia.org) and normalize them
     * to the domain (en.wikipedia). This method then instantiates a new EventWiki if one did not already exist.
     * @param string[] $wikis As retrieved by the form.
     * @param Event $event
     * @return EventWiki[]
     */
    private function normalizeEventWikis(array $wikis, Event $event): array
    {
        return array_map(function ($wiki) use ($event) {
            $domain = $this->ewRepo->getDomainFromEventWikiInput($wiki);

            $eventWiki = $this->ewRepo->findOneBy([
                'event' => $event,
                'domain' => $domain,
            ]);

            if (null === $eventWiki) {
                $eventWiki = new EventWiki($event, $domain);
            }

            return $eventWiki;
        }, $wikis);
    }
}
