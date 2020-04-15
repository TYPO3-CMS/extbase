<?php

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

namespace TYPO3\CMS\Extbase\Domain\Model;

use TYPO3\CMS\Extbase\Annotation as Extbase;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * This model represents a file mount.
 *
 * @deprecated since TYPO3 10.4, will be removed in version 11.0
 */
class FileMount extends AbstractEntity
{
    public function __construct()
    {
        trigger_error(
            __CLASS__ . ' is deprecated since TYPO3 10.4 and will be removed in version 11.0',
            E_USER_DEPRECATED
        );
    }

    /**
     * Title of the file mount.
     *
     * @var string
     * @Extbase\Validate("NotEmpty")
     */
    protected $title = '';

    /**
     * Description of the file mount.
     *
     * @var string
     */
    protected $description;

    /**
     * Path of the file mount.
     *
     * @var string
     * @Extbase\Validate("NotEmpty")
     */
    protected $path = '';

    /**
     * Determines whether the value of the path field is to be recognized as an absolute
     * path on the server or a path relative to the fileadmin/ subfolder to the website.
     *
     * If the value is true the path is an absolute one, otherwise the path is relative
     * the fileadmin.
     *
     * @var bool
     */
    protected $isAbsolutePath = false;

    /**
     * Determines whether this file mount should be read only.
     *
     * @var bool
     */
    protected $readOnly = false;

    /**
     * Getter for the title of the file mount.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Setter for the title of the file mount.
     *
     * @param string $value
     */
    public function setTitle($value)
    {
        $this->title = $value;
    }

    /**
     * Getter for the description of the file mount.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Setter for the description of the file mount.
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Getter for the path of the file mount.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Setter for the path of the file mount.
     *
     * @param string $value
     */
    public function setPath($value)
    {
        $this->path = $value;
    }

    /**
     * Getter for the is absolute path of the file mount.
     *
     * @return bool
     */
    public function getIsAbsolutePath()
    {
        return $this->isAbsolutePath;
    }

    /**
     * Setter for is absolute path of the file mount.
     *
     * @param bool $value
     */
    public function setIsAbsolutePath($value)
    {
        $this->isAbsolutePath = $value;
    }

    /**
     * Setter for the readOnly property of the file mount.
     *
     * @param bool $readOnly
     */
    public function setReadOnly($readOnly)
    {
        $this->readOnly = $readOnly;
    }

    /**
     * Getter for the readOnly property of the file mount.
     *
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }
}
