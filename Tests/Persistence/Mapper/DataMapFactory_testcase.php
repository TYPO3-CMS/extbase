<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Jochen Rau <jochen.rau@typoplanet.de>
*  All rights reserved
*
*  This class is a backport of the corresponding class of FLOW3. 
*  All credits go to the v5 team.
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

require_once(PATH_tslib . 'class.tslib_content.php');

class Tx_Extbase_Persistence_Mapper_DataMapFactory_testcase extends Tx_Extbase_BaseTestCase {
			
	/**
	 * @test
	 */
	public function setRelationsDetectsOneToOneRelationOfTypeSelect() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'select',
			'foreign_table' => 'tx_myextension_bar',
			'foreign_field' => 'parentid',
			'foreign_table_field' => 'parenttable',
			'maxitems' => '1'
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->once())->method('setOneToOneRelation');
		$mockDataMap->expects($this->never())->method('setOneToManyRelation');
		$mockDataMap->expects($this->never())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}
	
	/**
	 * @test
	 */
	public function setRelationsDetectsOneToOneRelationOfTypeSelectWithDefaultMaxitems() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'select',
			'foreign_table' => 'tx_myextension_bar',
			'foreign_field' => 'parentid',
			'foreign_table_field' => 'parenttable'
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->once())->method('setOneToOneRelation');
		$mockDataMap->expects($this->never())->method('setOneToManyRelation');
		$mockDataMap->expects($this->never())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}
	
	/**
	 * @test
	 */
	public function setRelationsDetectsOneToOneRelationOfTypeInline() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'inline',
			'foreign_table' => 'tx_myextension_bar',
			'foreign_field' => 'parentid',
			'foreign_table_field' => 'parenttable',
			'maxitems' => '1'
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->once())->method('setOneToOneRelation');
		$mockDataMap->expects($this->never())->method('setOneToManyRelation');
		$mockDataMap->expects($this->never())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}
	
	/**
	 * @test
	 */
	public function setRelationsDetectsOneToManyRelationOfTypeSelect() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'select',
			'foreign_table' => 'tx_myextension_bar',
			'foreign_field' => 'parentid',
			'foreign_table_field' => 'parenttable',
			'maxitems' => 9999
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->never())->method('setOneToOneRelation');
		$mockDataMap->expects($this->once())->method('setOneToManyRelation');
		$mockDataMap->expects($this->never())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}
	
	/**
	 * @test
	 */
	public function setRelationsDetectsOneToManyRelationWitTypeInline() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'inline',
			'foreign_table' => 'tx_myextension_bar',
			'foreign_field' => 'parentid',
			'foreign_table_field' => 'parenttable',
			'maxitems' => 9999
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->never())->method('setOneToOneRelation');
		$mockDataMap->expects($this->once())->method('setOneToManyRelation');
		$mockDataMap->expects($this->never())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}

	/**
	 * @test
	 */
	public function setRelationsDetectsManyToManyRelationOfTypeSelect() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'select',
			'foreign_table' => 'tx_myextension_bar',
			'MM' => 'tx_myextension_mm'
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->never())->method('setOneToOneRelation');
		$mockDataMap->expects($this->never())->method('setOneToManyRelation');
		$mockDataMap->expects($this->once())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}
	
	/**
	 * @test
	 */
	public function setRelationsDetectsManyToManyRelationOfTypeInlineWithIntermediateTable() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'inline',
			'foreign_table' => 'tx_myextension_righttable',
			'MM' => 'tx_myextension_mm'
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->never())->method('setOneToOneRelation');
		$mockDataMap->expects($this->never())->method('setOneToManyRelation');
		$mockDataMap->expects($this->once())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}
	
	/**
	 * @test
	 */
	public function setRelationsDetectsManyToManyRelationOfTypeInlineWithForeignSelector() {
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
	    $columnConfiguration = array(
			'type' => 'inline',
			'foreign_table' => 'tx_myextension_mm',
			'foreign_field' => 'uid_local',
			'foreign_selector' => 'uid_foreign'
			);
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('setOneToOneRelation', 'setOneToManyRelation', 'setManyToManyRelation'), array(), '', FALSE);
		$mockDataMap->expects($this->never())->method('setOneToOneRelation');
		$mockDataMap->expects($this->never())->method('setOneToManyRelation');
		$mockDataMap->expects($this->once())->method('setManyToManyRelation');
		$mockDataMap->_callRef('setRelations', $mockColumnMap, $columnConfiguration);
	}
	
	/**
	 * @test
	 */
	public function columnMapIsInitializedWithManyToManyRelationOfTypeSelect() {
		$leftColumnsDefinition = array(
			'rights' => array(
				'type' => 'select',
				'foreign_table' => 'tx_myextension_righttable',
				'foreign_table_where' => 'WHERE 1=1',
				'MM' => 'tx_myextension_mm',
				'MM_table_where' => 'WHERE 2=2',
				),
			);
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
		$mockColumnMap->expects($this->once())->method('setTypeOfRelation')->with($this->equalTo(Tx_Extbase_Persistence_Mapper_ColumnMap::RELATION_HAS_AND_BELONGS_TO_MANY));
		$mockColumnMap->expects($this->once())->method('setChildTableName')->with($this->equalTo('tx_myextension_righttable'));
		$mockColumnMap->expects($this->once())->method('setChildTableWhereStatement')->with($this->equalTo('WHERE 1=1'));
		$mockColumnMap->expects($this->once())->method('setChildSortbyFieldName')->with($this->equalTo('sorting'));
		$mockColumnMap->expects($this->once())->method('setParentKeyFieldName')->with($this->equalTo('uid_local'));
		$mockColumnMap->expects($this->never())->method('setParentTableFieldName');
		$mockColumnMap->expects($this->never())->method('setRelationTableMatchFields');
		$mockColumnMap->expects($this->never())->method('setRelationTableInsertFields');
		
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('dummy'), array(), '', FALSE);
		$mockDataMap->_callRef('setManyToManyRelation', $mockColumnMap, $leftColumnsDefinition['rights']);
	}
	
	/**
	 * @test
	 */
	public function columnMapIsInitializedWithOppositeManyToManyRelationOfTypeSelect() {
		$rightColumnsDefinition = array(
			'lefts' => array(
				'type' => 'select',
				'foreign_table' => 'tx_myextension_lefttable',
				'MM' => 'tx_myextension_mm',
				'MM_opposite_field' => 'rights'
				),
			);
		$leftColumnsDefinition['rights']['MM_opposite_field'] = 'opposite_field';		
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
		$mockColumnMap->expects($this->once())->method('setTypeOfRelation')->with($this->equalTo(Tx_Extbase_Persistence_Mapper_ColumnMap::RELATION_HAS_AND_BELONGS_TO_MANY));
		$mockColumnMap->expects($this->once())->method('setChildTableName')->with($this->equalTo('tx_myextension_lefttable'));
		$mockColumnMap->expects($this->once())->method('setChildTableWhereStatement')->with(NULL);
		$mockColumnMap->expects($this->once())->method('setChildSortbyFieldName')->with($this->equalTo('sorting_foreign'));
		$mockColumnMap->expects($this->once())->method('setParentKeyFieldName')->with($this->equalTo('uid_foreign'));
		$mockColumnMap->expects($this->never())->method('setParentTableFieldName');
		$mockColumnMap->expects($this->never())->method('setRelationTableMatchFields');
		$mockColumnMap->expects($this->never())->method('setRelationTableInsertFields');
		
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('dummy'), array(), '', FALSE);
		$mockDataMap->_callRef('setManyToManyRelation', $mockColumnMap, $rightColumnsDefinition['lefts']);
	}
	
	/**
	 * @test
	 */
	public function columnMapIsInitializedWithManyToManyRelationOfTypeInlineAndIntermediateTable() {
	    $leftColumnsDefinition = array(
			'rights' => array(
				'type' => 'inline',
				'foreign_table' => 'tx_myextension_righttable',
				'MM' => 'tx_myextension_mm',
				'foreign_sortby' => 'sorting'
				)
			);
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
		$mockColumnMap->expects($this->once())->method('setTypeOfRelation')->with($this->equalTo(Tx_Extbase_Persistence_Mapper_ColumnMap::RELATION_HAS_AND_BELONGS_TO_MANY));
		$mockColumnMap->expects($this->once())->method('setChildTableName')->with($this->equalTo('tx_myextension_righttable'));
		$mockColumnMap->expects($this->once())->method('setChildTableWhereStatement');
		$mockColumnMap->expects($this->once())->method('setChildSortbyFieldName')->with($this->equalTo('sorting'));
		$mockColumnMap->expects($this->once())->method('setParentKeyFieldName')->with($this->equalTo('uid_local'));
		$mockColumnMap->expects($this->never())->method('setParentTableFieldName');
		$mockColumnMap->expects($this->never())->method('setRelationTableMatchFields');
		$mockColumnMap->expects($this->never())->method('setRelationTableInsertFields');
		
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('getColumnsDefinition'), array(), '', FALSE);
		$mockDataMap->expects($this->never())->method('getColumnsDefinition');
		$mockDataMap->_callRef('setManyToManyRelation', $mockColumnMap, $leftColumnsDefinition['rights']);
	}

	/**
	 * @test
	 */
	public function columnMapIsInitializedWithManyToManyRelationOfTypeInlineAndForeignSelector() {
	    $leftColumnsDefinition = array(
			'rights' => array(
				'type' => 'inline',
				'foreign_table' => 'tx_myextension_mm',
				'foreign_field' => 'uid_local',
				'foreign_selector' => 'uid_foreign',
				'foreign_sortby' => 'sorting'
				)
			);
	    $relationTableColumnsDefiniton = array(
			'uid_local' => array(
				'config' => array('foreign_table' => 'tx_myextension_localtable')
				),
			'uid_foreign' => array(
				'config' => array('foreign_table' => 'tx_myextension_righttable')
				)
			);
	    $rightColumnsDefinition = array(
			'lefts' => array(
				'type' => 'inline',
				'foreign_table' => 'tx_myextension_mm',
				'foreign_field' => 'uid_foreign',
				'foreign_selector' => 'uid_local',
				'foreign_sortby' => 'sorting_foreign'
				)
			);
		$mockColumnMap = $this->getMock('Tx_Extbase_Persistence_Mapper_ColumnMap', array(), array(), '', FALSE);
		$mockColumnMap->expects($this->once())->method('setTypeOfRelation')->with($this->equalTo(Tx_Extbase_Persistence_Mapper_ColumnMap::RELATION_HAS_AND_BELONGS_TO_MANY));
		$mockColumnMap->expects($this->once())->method('setChildTableName')->with($this->equalTo('tx_myextension_righttable'));
		$mockColumnMap->expects($this->never())->method('setChildTableWhereStatement');
		$mockColumnMap->expects($this->once())->method('setChildSortbyFieldName')->with($this->equalTo('sorting'));
		$mockColumnMap->expects($this->once())->method('setParentKeyFieldName')->with($this->equalTo('uid_local'));
		$mockColumnMap->expects($this->never())->method('setParentTableFieldName');
		$mockColumnMap->expects($this->never())->method('setRelationTableMatchFields');
		$mockColumnMap->expects($this->never())->method('setRelationTableInsertFields');
		
		$mockDataMap = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Persistence_Mapper_DataMapFactory'), array('getColumnsDefinition'), array(), '', FALSE);
		$mockDataMap->expects($this->once())->method('getColumnsDefinition')->with($this->equalTo('tx_myextension_mm'))->will($this->returnValue($relationTableColumnsDefiniton));
		$mockDataMap->_callRef('setManyToManyRelation', $mockColumnMap, $leftColumnsDefinition['rights']);
	}
	
}
?>