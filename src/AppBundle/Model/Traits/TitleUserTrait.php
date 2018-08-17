<?php
/**
 * This file contains only the TitleUserTrait trait.
 */

namespace AppBundle\Model\Traits;

use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The TitleUserTrait refactors out common methods between
 * Program and Event. These methods pertain to titles of the entity,
 * and the associated user class (Organizer or Participant).
 */
trait TitleUserTrait
{
    /**
     * The class name of users associated with Events,
     * either 'Organizer' or 'Participant'.
     * @abstract
     * @return string
     */
    abstract public function getUserClassName();

    /*********
     * TITLE *
     *********/

    /**
     * Get the title of this Program.
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get the display variant of the program title.
     * @return string
     */
    public function getDisplayTitle()
    {
        return str_replace('_', ' ', $this->title);
    }

    /**
     * Set the title of this Program.
     * @param string $title
     */
    public function setTitle($title)
    {
        // Use underscores instead of spaces.
        $this->title = str_replace(' ', '_', trim($title));
    }

    /**
     * Validates that the title is not a reserved string.
     * @Assert\Callback
     * @param ExecutionContext $context Supplied by Symfony.
     */
    public function validateUnreservedTitle(ExecutionContext $context)
    {
        if (in_array($this->title, ['edit', 'new', 'delete', 'process', 'api', 'revisions', 'participants'])) {
            $context->buildViolation('error-title-reserved')
                ->setParameter(0, '<code>edit</code>, <code>delete</code>, <code>process</code>, '.
                    '<code>api</code>, <code>revisions</code>, <code>participants</code>')
                ->atPath('title')
                ->addViolation();
        }
    }

    /**
     * Validates that the title does not contain invalid characters.
     * @Assert\Callback
     * @param ExecutionContext $context Supplied by Symfony.
     */
    public function validateCharacters(ExecutionContext $context)
    {
        if (preg_match('/[\/]/', $this->title) === 1) {
            $context->buildViolation('error-title-invalid-chars')
                ->setParameter(0, '<code>/</code>')
                ->atPath('title')
                ->addViolation();
        }
    }

    /*****************************
     * ORGANIZERS / PARTICIPANTS *
     *****************************/

    /**
     * Validates that the model's users (Organizers or Participants) have user IDs.
     * @Assert\Callback
     * @param ExecutionContext $context Supplied by Symfony.
     */
    public function validateUsers(ExecutionContext $context)
    {
        $userIds = $this->{'get'.$this->getUserClassName().'Ids'}();
        $numEmpty = count($userIds) - count(array_filter($userIds));
        if ($numEmpty > 0) {
            $context->buildViolation('error-usernames')
                ->setParameter(0, $numEmpty)
                ->atPath(strtolower($this->getUserClassName().'s'))
                ->addViolation();
        }
    }
}
