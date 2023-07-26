<?php

declare(strict_types=1);

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

namespace TYPO3\CMS\Extbase\Tests\Functional\Persistence;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Tests\BlogExample\Domain\Model\Blog;
use TYPO3Tests\BlogExample\Domain\Model\Enum\Salutation;
use TYPO3Tests\BlogExample\Domain\Model\Person;
use TYPO3Tests\BlogExample\Domain\Repository\BlogRepository;

final class AddTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/sysext/extbase/Tests/Functional/Fixtures/Extensions/blog_example'];

    protected PersistenceManager $persistentManager;
    protected BlogRepository $blogRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persistentManager = $this->get(PersistenceManager::class);
        $this->blogRepository = $this->get(BlogRepository::class);
        $GLOBALS['BE_USER'] = new BackendUserAuthentication();

        $request = (new ServerRequest())->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }

    /**
     * @test
     */
    public function addSimpleObjectTest(): void
    {
        $newBlogTitle = 'aDi1oogh';
        $newBlog = new Blog();
        $newBlog->setTitle($newBlogTitle);

        $this->blogRepository->add($newBlog);
        $this->persistentManager->persistAll();

        $queryBuilder = (new ConnectionPool())->getQueryBuilderForTable('tx_blogexample_domain_model_blog');
        $queryBuilder->getRestrictions()
            ->removeAll();
        $newBlogCount = $queryBuilder
            ->count('*')
            ->from('tx_blogexample_domain_model_blog')
            ->where(
                $queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter($newBlogTitle)
                )
            )
            ->executeQuery()
            ->fetchOne();
        self::assertEquals(1, $newBlogCount);
    }

    /**
     * @test
     */
    public function addObjectSetsDefaultLanguageTest(): void
    {
        $newBlogTitle = 'aDi1oogh';
        $newBlog = new Blog();
        $newBlog->setTitle($newBlogTitle);

        $this->blogRepository->add($newBlog);
        $this->persistentManager->persistAll();

        $queryBuilder = (new ConnectionPool())->getQueryBuilderForTable('tx_blogexample_domain_model_blog');
        $queryBuilder->getRestrictions()
            ->removeAll();
        $newBlogRecord = $queryBuilder
            ->select('*')
            ->from('tx_blogexample_domain_model_blog')
            ->where(
                $queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter($newBlogTitle)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
        self::assertEquals(0, $newBlogRecord['sys_language_uid']);
    }

    /**
     * @test
     */
    public function addObjectSetsDefinedLanguageTest(): void
    {
        $newBlogTitle = 'aDi1oogh';
        $newBlog = new Blog();
        $newBlog->setTitle($newBlogTitle);
        $newBlog->_setProperty(AbstractDomainObject::PROPERTY_LANGUAGE_UID, -1);

        $this->blogRepository->add($newBlog);
        $this->persistentManager->persistAll();

        $queryBuilder = (new ConnectionPool())->getQueryBuilderForTable('tx_blogexample_domain_model_blog');
        $queryBuilder->getRestrictions()
            ->removeAll();
        $newBlogRecord = $queryBuilder
            ->select('*')
            ->from('tx_blogexample_domain_model_blog')
            ->where(
                $queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter($newBlogTitle)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
        self::assertEquals(-1, $newBlogRecord['sys_language_uid']);
    }

    /**
    * @test
    */
    public function addObjectSetsNullAsNullForSimpleTypes(): void
    {
        $newBlogTitle = 'aDi1oogh';
        $newBlog = new Blog();
        $newBlog->setTitle($newBlogTitle);
        $newBlog->setSubtitle('subtitle');

        $this->blogRepository->add($newBlog);
        $this->persistentManager->persistAll();

        // make sure null can be set explicitly
        $insertedBlog = $this->blogRepository->findByUid(1);
        $insertedBlog->setSubtitle(null);
        $this->blogRepository->update($insertedBlog);
        $this->persistentManager->persistAll();

        $queryBuilder = (new ConnectionPool())->getQueryBuilderForTable('tx_blogexample_domain_model_blog');
        $queryBuilder->getRestrictions()
            ->removeAll();
        $newBlogRecord = $queryBuilder
            ->select('*')
            ->from('tx_blogexample_domain_model_blog')
            ->where(
                $queryBuilder->expr()->eq(
                    'subtitle',
                    $queryBuilder->createNamedParameter($newBlogTitle)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
        self::assertNull($newBlogRecord['subtitle'] ?? null);
    }

    /**
     * @test
     */
    public function addObjectPersistsEnumProperty(): void
    {
        $person = new Person();
        $person->setSalutation(Salutation::MR);

        $this->persistentManager->add($person);
        $this->persistentManager->persistAll();
        unset($person);

        /** @var Person $person */
        $person = $this->persistentManager->getObjectByIdentifier(1, Person::class);

        self::assertSame(Salutation::MR, $person->getSalutation());
    }
}
