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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Tests\BlogExample\Domain\Model\Post;
use TYPO3Tests\BlogExample\Domain\Repository\PostRepository;

final class TranslationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3/sysext/extbase/Tests/Functional/Fixtures/Extensions/blog_example',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TranslationTestImport.csv');

        $context = $this->get(Context::class);
        $context->setAspect('language', new LanguageAspect(0, 0, LanguageAspect::OVERLAYS_OFF));
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.typoscript', $frontendTypoScript);

        // ConfigurationManager is used by PersistenceManager to retrieve configuration.
        // We set a proper extensionName and pluginName for the ConfigurationManager singleton
        // here, to not run into warnings due to incomplete test setup.
        $configurationManager = $this->get(ConfigurationManager::class);
        $configurationManager->setConfiguration([
            'extensionName' => 'blog_example',
            'pluginName' => 'test',
        ]);
    }

    /**
     * Tests if repository returns correct number of posts in the default language
     */
    #[Test]
    public function countReturnsCorrectNumberOfPosts(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(0, 0, LanguageAspect::OVERLAYS_OFF));
        $postCount = $query->execute()->count();
        self::assertSame(4, $postCount);
    }

    /**
     * Test for fetching records with disabled overlay
     */
    #[Test]
    public function countReturnsCorrectNumberOfPostsInEnglishLanguage(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_OFF));
        $postCount = $query->execute()->count();
        self::assertSame(2, $postCount);
    }

    #[Test]
    public function countReturnsCorrectNumberOfPostsInGreekLanguage(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_OFF));
        $postCount = $query->execute()->count();
        self::assertSame(2, $postCount);
    }

    #[Test]
    public function fetchingPostsReturnsEnglishPostsWithFallback(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_ON));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(2, $posts);
        self::assertSame('A EN:Post2', $posts[0]->getTitle());
        self::assertSame('B EN:Post1', $posts[1]->getTitle());
    }

    #[Test]
    public function fetchingPostsByInClauseReturnsDefaultPostsWithFallback(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(false);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_MIXED));
        $query->matching($query->in('uid', [4]));
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(1, $posts);
        self::assertSame('Post2', $posts[0]->getTitle());
    }

    /**
     * This tests shows overlays in action
     */
    #[Test]
    public function fetchingPostsReturnsGreekPostsWithFallback(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_OFF));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(2, $posts);
        self::assertSame('GR:Post1', $posts[0]->getTitle());
        self::assertSame('GR:Post11', $posts[1]->getTitle());
    }

    /**
     * This tests shows overlay 'hideNonTranslated' in action
     */
    #[Test]
    public function fetchingPostsReturnsGreekPostsWithHideNonTranslated(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_ON));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(2, $posts);
        self::assertSame('GR:Post1', $posts[0]->getTitle());
        self::assertSame('GR:Post11', $posts[1]->getTitle());
    }

    public static function fetchingTranslatedPostByUidDataProvider(): array
    {
        return [
            'with one id' => [
                'input' => [12],
                'expectedTitles' => ['GR:Post11'],
            ],
            'with two ids' => [
                'input' => [12, 1],
                'expectedTitles' => ['GR:Post11', 'GR:Post1'],
            ],
        ];
    }

    #[DataProvider('fetchingTranslatedPostByUidDataProvider')]
    #[Test]
    public function fetchingTranslatedPostByInClauseWithStrictLanguageSettings(array $input, array $expectedTitles): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_ON));
        $query->matching($query->in('uid', $input));
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        // @todo: wrong assertion
        // We're simulating a strict language configuration where a blog post (uid=12 or uid=1) has been translated to another
        // language. However, Extbase is not able to find the translated record via ->in() and therefore returns an
        // empty result set. This will be fixed with https://review.typo3.org/c/Packages/TYPO3.CMS/+/67893
        self::assertCount(0, $posts);
        // self::assertCount(count($expectedTitles), $posts);
        // self::assertEqualsCanonicalizing($expectedTitles, array_map(static fn(Post $post): string => $post->getTitle(), $posts));
    }

    #[DataProvider('fetchingTranslatedPostByUidDataProvider')]
    #[Test]
    public function fetchingTranslatedPostByEqualsUidClauseWithStrictLanguageSettings(array $input, array $expectedTitles): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_ON));
        $constraints = [];
        foreach ($input as $uid) {
            $constraints[] = $query->equals('uid', $uid);
        }
        $query->matching($query->logicalOr(...$constraints));
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        // @todo: wrong assertion
        // We're simulating a strict language configuration where a blog post (uid=12 or uid=1) has been translated to another
        // language. However, Extbase is not able to find the translated record via ->equals(uid=12 OR uid=1 OR ...) and therefore returns an
        // empty result set. This will be fixed with https://review.typo3.org/c/Packages/TYPO3.CMS/+/67893
        self::assertCount(0, $posts);
        // self::assertCount(count($expectedTitles), $posts);
        // self::assertEqualsCanonicalizing($expectedTitles, array_map(static fn(Post $post): string => $post->getTitle(), $posts));
    }

    #[Test]
    public function orderingByTitleRespectsEnglishTitles(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_OFF));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(2, $posts);
        self::assertSame('A EN:Post2', $posts[0]->getTitle());
        self::assertSame('B EN:Post1', $posts[1]->getTitle());
    }

    /**
     * This test shows that ordering by blog title works
     * however the default language blog title is used
     */
    #[Test]
    public function orderingByBlogTitle(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_OFF));
        $query->setOrderings([
            'blog.title' => QueryInterface::ORDER_ASCENDING,
            'uid' => QueryInterface::ORDER_ASCENDING,
        ]);
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(2, $posts);
        self::assertSame('B EN:Post1', $posts[0]->getTitle());
        self::assertSame('A EN:Post2', $posts[1]->getTitle());
    }

    /**
     * This test checks whether setIgnoreEnableFields(true) affects the query
     * It's expected that when ignoring enable fields, the hidden record is also returned.
     * This is related to https://forge.typo3.org/issues/68672
     */
    #[Test]
    public function fetchingHiddenPostsWithIgnoreEnableField(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setIgnoreEnableFields(true);
        $querySettings->setLanguageAspect(new LanguageAspect(0, 0, LanguageAspect::OVERLAYS_ON));
        // we need it to have stable results on pgsql
        $query->setOrderings(['uid' => QueryInterface::ORDER_ASCENDING]);
        /** @var Post[] $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(5, $posts);
        self::assertSame('Post10', $posts[3]->getTitle());
    }

    /**
     * This test checks whether setIgnoreEnableFields(true) affects translated record too.
     * It's expected that when ignoring enable fields, the hidden translated record is shown.
     */
    #[Test]
    public function fetchingHiddenPostsReturnsHiddenOverlay(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setIgnoreEnableFields(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_OFF));
        // We need it to have stable results on pgsql
        $query->setOrderings(['uid' => QueryInterface::ORDER_ASCENDING]);
        /** @var Post[] $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(3, $posts);
        self::assertSame('GR:Post1', $posts[0]->getTitle());
        self::assertSame('GR:Post10', $posts[1]->getTitle());
        self::assertSame('GR:Post11', $posts[2]->getTitle());
    }

    /**
     * This test checks whether setIgnoreEnableFields(true) affects translated record too.
     * It's expected that when ignoring enable fields, the hidden translated record is shown.
     * This is related to https://forge.typo3.org/issues/68672
     *
     * This tests documents current, buggy behaviour!
     */
    #[Test]
    public function fetchingHiddenPostsReturnsHiddenOverlayOverlayEnabled(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setIgnoreEnableFields(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_MIXED));
        //we need it to have stable results on pgsql
        $query->setOrderings(['uid' => QueryInterface::ORDER_ASCENDING]);
        /** @var Post[] $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(5, $posts);
        self::assertSame('GR:Post1', $posts[0]->getTitle());
        self::assertSame('Post2', $posts[1]->getTitle());
        self::assertSame('Post3', $posts[2]->getTitle());
        // once the issue is fixed this assertions should be GR:Post10
        self::assertSame('Post10', $posts[3]->getTitle());
        self::assertSame('GR:Post11', $posts[4]->getTitle());
    }

    /**
     * Test checking if we can query db records by translated fields
     */
    #[Test]
    public function fetchingTranslatedPostByTitle(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_OFF));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $query->matching($query->equals('title', 'GR:Post1'));
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(1, $posts);
        self::assertSame('GR:Post1', $posts[0]->getTitle());
    }

    /**
     * Test checking if we can query db records by value of the child object
     * Note that only child objects from language 0 are taken into account
     */
    #[Test]
    public function fetchingTranslatedPostByBlogTitle(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_OFF));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $query->matching($query->equals('blog.title', 'Blog1'));
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(2, $posts);
        self::assertSame('GR:Post1', $posts[0]->getTitle());
        self::assertSame('GR:Post11', $posts[1]->getTitle());
    }

    #[Test]
    public function fetchingPostByTagName(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setRespectStoragePage(false);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(0, 0, LanguageAspect::OVERLAYS_OFF));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $query->matching($query->equals('tags.name', 'Tag1'));
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(3, $posts);
        self::assertSame('Post1', $posts[0]->getTitle());
    }

    #[Test]
    public function fetchingTranslatedPostByTagName(): void
    {
        $query = $this->get(PostRepository::class)->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setRespectStoragePage(false);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageAspect(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_OFF));
        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $query->matching($query->equals('tags.name', 'Tag1'));
        /** @var Post[]|array $posts */
        $posts = $query->execute()->toArray();
        self::assertCount(2, $posts);
        self::assertSame('A EN:Post2', $posts[0]->getTitle());
        self::assertCount(1, $posts[0]->getTags());
        self::assertSame('B EN:Post1', $posts[1]->getTitle());
        self::assertCount(2, $posts[1]->getTags());
    }
}
