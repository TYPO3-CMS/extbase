<?php
namespace ExtbaseTeam\BlogExample\Domain\Model;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * A person - acting as author
 */
class Person extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * @var string
     */
    protected $firstname = '';

    /**
     * @var string
     */
    protected $lastname = '';

    /**
     * @var string
     */
    protected $email = '';

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\ExtbaseTeam\BlogExample\Domain\Model\Tag>
     */
    protected $tags;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\ExtbaseTeam\BlogExample\Domain\Model\Tag>
     */
    protected $tagsSpecial;

    /**
     * Constructs a new Person
     */
    public function __construct($firstname, $lastname, $email)
    {
        $this->setFirstname($firstname);
        $this->setLastname($lastname);
        $this->setEmail($email);

        $this->tags = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $this->tagsSpecial = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    /**
     * Sets this persons's firstname
     *
     * @param string $firstname The person's firstname
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    /**
     * Returns the person's firstname
     *
     * @return string The persons's firstname
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Sets this persons's lastname
     *
     * @param string $lastname The person's lastname
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    /**
     * Returns the person's lastname
     *
     * @return string The persons's lastname
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Returns the person's full name
     *
     * @return string The persons's lastname
     */
    public function getFullName()
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    /**
     * Sets this persons's email address
     *
     * @param string $email The person's email address
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Returns the person's email address
     *
     * @return string The persons's email address
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage|\ExtbaseTeam\BlogExample\Domain\Model\Tag[]
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $tags
     */
    public function setTags(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $tags)
    {
        $this->tags = $tags;
    }

    /**
     * @param Tag $tag
     */
    public function addTag(Tag $tag)
    {
        $this->tags->attach($tag);
    }

    /**
     * @param Tag $tag
     */
    public function removeTag(Tag $tag)
    {
        $this->tags->detach($tag);
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage|\ExtbaseTeam\BlogExample\Domain\Model\Tag[]
     */
    public function getTagsSpecial()
    {
        return $this->tagsSpecial;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $tagsSpecial
     */
    public function setTagsSpecial(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $tagsSpecial)
    {
        $this->tagsSpecial = $tagsSpecial;
    }

    /**
     * @param Tag $tag
     */
    public function addTagSpecial(Tag $tag)
    {
        $this->tagsSpecial->attach($tag);
    }

    /**
     * @param Tag $tag
     */
    public function removeTagSpecial(Tag $tag)
    {
        $this->tagsSpecial->detach($tag);
    }
}
