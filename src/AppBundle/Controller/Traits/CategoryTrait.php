<?php
/**
 * This file contains only the CategoryTrait trait.
 */

namespace AppBundle\Controller\Traits;

use AppBundle\Form\CategoryType;
use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\EventWiki;
use AppBundle\Repository\EventCategoryRepository;
use AppBundle\Repository\EventWikiRepository;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * The CategoryTrait handles the category form on the event page.
 */
trait CategoryTrait
{
    /**
     * Get the category list form. Shown on the 'show' page.
     * @param Event $event
     * @return Form
     */
    protected function getCategoryForm(Event $event)
    {
        /** @var FormBuilder $builder */
        $builder = $this->createFormBuilder($event);

//        // Add blank category if none already exists, so there will be an emtpy row ready to fill out.
//        if ($event->getNumCategories() === 0) {
//            $categories[] = new EventCategory(new EventWiki($event));
//        }

//        $builder->add('categories', CollectionType::class, [
//                'entry_type' => CategoryType::class,
//                'allow_add' => true,
//                'allow_delete' => true,
//                'empty_data' => '',
//                'required' => false,
//                'constraints' => [new Valid()],
////                'data' => [],
////                'data_class' => null,
//            ])
//            ->add('submit', SubmitType::class);
////            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onCategoryPreSubmit']);
//
//        $builder->get('categories')
//            ->addModelTransformer($this->getCategoryCallbackTransformer($event));

        return $builder->getForm();
    }

    /**
     * Handle submission of form to add/remove categories.
     * @return FormInterface|RedirectResponse
     */
    protected function handleCategoryForm()
    {
        $form = $this->getCategoryForm($this->event);
        $form->handleRequest($this->request);

        dump($form->isSubmitted());

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $form->getData();

            // Clear statistics and child wikis as the data will now be stale.
            $event->clearStatistics();
            $event->clearChildWikis();

            $this->em->persist($event);
            $this->em->flush();

            return $this->redirectToRoute('Event', [
                'programTitle' => $event->getProgram()->getTitle(),
                'eventTitle' => $event->getTitle(),
            ]);
        }

        return $form;
    }

    /**
     * Given a row from EventCategoryRepository::getRowsFromUsernames(), find or instantiate a new Category.
     * @param EventWiki $eventWiki
     * @param string[] $row As fetched from CategoryRepository::getRowsFromUsernames().
     * @param EventCategoryRepository $categoryRepo
     * @return EventCategory
     */
    private function getCategoryFromRow(EventWiki $eventWiki, $row, EventCategoryRepository $categoryRepo)
    {
        if ($row['user_id'] === null) {
            // Username is invalid, so just return a new Participant
            // without a user ID so that the form can produce errors.
            $category = new EventCategory($eventWiki);
        } else {
            // Otherwise we need find the one that exists in grantmetrics.
            $category = $categoryRepo->findOneBy([
                'categoryId' => $row['cat_id'],
                'eventWiki' => $eventWiki,
            ]);

            if ($category === null) {
                // Participant doesn't exist in grantmetrics yet,
                // so we'll create a new, blank Participant.
                $category = new EventCategory($eventWiki);
                $category->setUserId($row['user_id']);
            }
        }

        $category->setUsername($row['user_name']);

        return $categories;
    }

    /**
     * Transform category data to or from the form. This essentially pulls in the category title from the category ID,
     * and sets the category ID before persisting so that the title can be validated.
     * @param Event $event
     * @return CallbackTransformer
     */
    private function getCategoryCallbackTransformer(Event $event)
    {
        return new CallbackTransformer(
            // Transform to the form.
            function (array $categoryObjects) {
                /** @var EventCategoryRepository $catRepo */
                $catRepo = $this->em->getRepository(EventCategory::class);
                $catRepo->setContainer($this->container);

                /** @var EventWikiRepository $ewRepo */
                $ewRepo = $this->em->getRepository(EventWiki::class);
                $ewRepo->setContainer($this->container);

                $ret = array_map(function (EventCategory $category) use ($catRepo, $ewRepo) {
                    $title = $catRepo->getCategoryNameFromId(
                        $ewRepo->getDbName($category->getWiki()),
                        $category->getCategoryId()
                    );

                    $category->setTitle($title);
                }, $categoryObjects);

                dump($ret);

                return $ret;
            },
            // Transform from the form.
            function (array $categoryNames) use ($event) {
                /** @var EventCategoryRepository $categoryRepo */
                $categoryRepo = $this->em->getRepository(EventCategory::class);
                $categoryRepo->setContainer($this->container);

                // Get the rows for each requested username.
                $rows = $categoryRepo->getCategoryIdsFromNames('dbname', $categoryNames);

                // Should be in alphabetical order, the same order we'll show it
                // to the user in the returned form.
                usort($rows, function ($a, $b) {
                    return strnatcmp($a['user_name'], $b['user_name']);
                });

                $categorys = [];

                // Create or get Participants from the usernames.
                foreach ($rows as $row) {
                    $category = $this->getCategoryFromRow($event, $row, $categoryRepo);
                    $event->addParticipant($category);
                    $categorys[] = $category;
                }

                return $categorys;
            }
        );
    }
}
