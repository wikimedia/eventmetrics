<?php
/**
 * This file contains only the Organizer class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * An Organizer is a user who organizes one or more programs.
 * @ORM\Entity
 * @ORM\Table(
 *     name="organizer",
 *     indexes={
 *         @ORM\Index(name="org_user", columns={"org_user_id"})
 *     },
 *     options={"engine":"InnoDB"}
 * )
 */
class Organizer extends Model
{
    /**
     * @ORM\Id
     * @ORM\Column(name="org_id", type="integer")
     * @ORM\GeneratedValue
     * @var int Primary key.
     */
    protected $id;

    /**
     * @ORM\Column(name="org_user_id", type="integer")
     * @var int Corresponds to the `gu_id` column in `centralauth`.`globaluser`.
     */
    protected $userId;

    /**
     * Many Organizers have many Programs.
     * @ORM\ManyToMany(targetEntity="Program", mappedBy="organizers")
     * @var ArrayCollection|Program[] Programs overseen by this organizer.
     */
    private $programs;

    /**
     * Organizer constructor.
     * @param int $userId ID of the user, corresponds with `centralauth`.`globaluser`.
     */
    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->programs = new ArrayCollection();
    }

    /**
     * Get the user ID of the Organizer.
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Get a list of programs that this organizer oversees.
     * @return ArrayCollection|Program[]
     */
    public function getPrograms()
    {
        return $this->programs;
    }

    /**
     * Associate this Organizer with a program.
     * @param Program $program
     */
    public function addProgram(Program $program)
    {
        if ($this->programs->contains($program)) {
            return;
        }
        $this->programs->add($program);
        $program->addOrganizer($this);
    }

    /**
     * Remove association of this Organizer with the given program.
     * @param Program $program
     */
    public function removeProgram(Program $program)
    {
        if (!$this->programs->contains($program)) {
            return;
        }
        $this->programs->removeElement($program);
        $program->removeOrganizer($this);
    }
}
