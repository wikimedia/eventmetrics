<?php
/**
 * This file contains only the CategoriesType class.
 */

declare(strict_types=1);

namespace AppBundle\Form;

use AppBundle\Model\EventCategory;
use AppBundle\Repository\EventCategoryRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * A CategoriesType is the type of Form used to enter categories.
 */
class CategoriesType extends AbstractType
{
    /** @var EventCategoryRepository */
    private $ecRepo;

    /**
     * CategoryType constructor.
     * @param EventCategoryRepository $ecRepo
     */
    public function __construct(EventCategoryRepository $ecRepo)
    {
        $this->ecRepo = $ecRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('categories', CollectionType::class, [
                'entry_type' => CategoryType::class,
                'entry_options' => [
                    'event' => $options['event'],
                    'required' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'delete_empty' => function (EventCategory $category = null) {
                    return null === $category || (empty($category->getTitle()) && empty($category->getDomain()));
                },
                'constraints' => [new Valid()],
            ])
            ->add('submit', SubmitType::class)
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);

        $builder->get('categories')
            ->addModelTransformer($this->getCategoryCallbackTransformer());
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'categoryForm';
    }

    /**
     * In EventController, we pass in a reference to the Event object. We must configure it as a valid form 'option'.
     * @see https://symfony.com/doc/current/form/use_empty_data.html
     *
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'event' => null,
        ]);
    }

    /**
     * Sanitize form submission data before the EventCategories are created.
     * @param FormEvent $formEvent
     */
    public function onPreSubmit(FormEvent $formEvent): void
    {
        $event = $formEvent->getData();

        if (!isset($event['categories'])) {
            // Empty, no action needed.
            return;
        }

        $categories = [];

        // Remove empty elements and remove duplicates.
        foreach ($event['categories'] as $index => $category) {
            // We have to underscore the title here (in addition to EventCategory::setTitle()) because we are
            // checking for duplicates, working with the raw form input and not EventCategory objects.
            $category['title'] = str_replace(' ', '_', trim($category['title']));
            if (!in_array($category, $categories) && $category['title'].$category['domain'] !== '') {
                // We must use the original index to reference the right entity on form submission.
                $categories[$index] = $category;
            }
        }

        $event['categories'] = $categories;

        $formEvent->setData($event);
    }

    /**
     * Used to validate category titles, setting their titles to blank if invalid.
     * Symfony will still show the invalid value in the view.
     * @return CallbackTransformer
     */
    private function getCategoryCallbackTransformer(): CallbackTransformer
    {
        return new CallbackTransformer(
            // Transform to the form.
            function (Collection $categories) {
                // No transformation needed.
                return $categories;
            },
            // Transform from the form.
            function (Collection $categories) {
                return $categories->map(function (EventCategory $category) {
                    // Fetch and set the category ID, which may be null (category does not exist).
                    $catId = $this->ecRepo->getCategoryId($category->getDomain(), $category->getTitle());
                    $category->setCategoryId($catId);
                    return $category;
                });
            }
        );
    }
}
