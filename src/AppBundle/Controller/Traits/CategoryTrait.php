<?php
/**
 * This file contains only the CategoryTrait trait.
 */

namespace AppBundle\Controller\Traits;

use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The CategoryTrait handles the category form on the event page.
 */
trait CategoryTrait
{
    /**
     * Handle submission of form to add/remove categories.
     * @return void|RedirectResponse
     */
    protected function handleCategoryFormSubmission()
    {
        $categoryForm = $this->request->get('categoryForm');

        // First make sure the categoryForm was submitted.
        if (!$this->request->isMethod('POST') || null === $categoryForm ||
            !$this->isCsrfTokenValid('authenticate', $this->request->get('_csrf_token'))
        ) {
            return;
        }

        // Parses the form and sets properties on the Event and associated Models.
        $this->updateEventFromCategoryForm($this->event, $categoryForm);
    }

    /**
     * Create or update EventCategories on the Event.
     * @param Event $event
     * @param array $formData
     */
    private function updateEventFromCategoryForm(Event $event, array $formData)
    {
        foreach ($formData['categories'] as $index => $title) {
            $wiki = $event->getWikiByDomain($formData['wikis'][$index]);
            $category = new EventCategory($wiki);
            $category->setTitle($title);
        }

        $errors = $this->validator->validate($event);

        // Clear statistics as the data will now be stale.
        $event->clearStatistics();
        // FIXME: child wikis can't be cleared because the category may be associated with them!
        // FIXME: this also means the associated EventWiki may be cleared.
//        $event->clearChildWikis();
        $this->em->persist($event);
        $this->em->flush();
    }
}
