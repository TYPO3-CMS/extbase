<?php
namespace ExtbaseTeam\BlogExample\Domain\Model;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Jochen Rau <jochen.rau@typoplanet.de>
 *  (c) 2011 Bastian Waidelich <bastian@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * A blog post tag
 */
class Tag extends \TYPO3\CMS\Extbase\DomainObject\AbstractValueObject {

	/**
	 * @var string
	 */
	protected $name = '';

	/**
	 * Constructs this tag
	 *
	 * @param $name
	 */
	public function __construct($name) {
		$this->name = $name;
	}

	/**
	 * Returns this tag's name
	 *
	 * @return string This tag's name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Returns this tag as a formatted string
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getName();
	}
}
?>