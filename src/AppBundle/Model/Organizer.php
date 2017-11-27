<?php
/**
 * This file contains only the Organizer class.
 */

namespace AppBundle\Model;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An Organizer is a user who organizes one or more programs.
 * @ORM\Entity
 * @ORM\Table(
 *     name="organizer",
 *     indexes={
 *         @ORM\Index(name="org_user", columns={"org_user_id"})
 *     },
 *     uniqueConstraints={@ORM\UniqueConstraint(name="org_user_id_uniq", columns={"org_user_id"})},
 *     options={"engine":"InnoDB"}
 * )
 * @ORM\Entity(repositoryClass="AppBundle\Repository\OrganizerRepository")
 */
class Organizer
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
     * @Assert\NotBlank()
     * @var int Corresponds to the `gu_id` column in `centralauth`.`globaluser`.
     */
    protected $userId;

    /**
     * @var string Username retrieved using the $userId.
     */
    protected $username;

    /**
     * Many Organizers have many Programs.
     * @ORM\ManyToMany(targetEntity="Program", mappedBy="organizers", cascade={"persist"})
     * @var ArrayCollection|Program[] Programs overseen by this organizer.
     */
    protected $programs;

    /**
     * Organizer constructor.
     * @param string $usernameOrId User's global user ID or username.
     */
    public function __construct($usernameOrId)
    {
        $this->programs = new ArrayCollection();

        if (is_int($usernameOrId)) {
            $this->userId = $usernameOrId;
        } else {
            $this->username = $usernameOrId;
        }
    }

    /**
     * Get the ID of the organizer.
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Does this organizer already exists in the database?
     * @return bool
     */
    public function exists()
    {
        return isset($this->id);
    }

    /**
     * Get the user ID of the Organizer.
     * Corresponds with `gu_id` on `centralauth`.`globaluser`.
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set the user ID.
     * Corresponds with `gu_id` on `centralauth`.`globaluser`.
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Get the username.
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set the username.
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
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
