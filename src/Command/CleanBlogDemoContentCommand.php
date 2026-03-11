<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Command;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'aanwik:clean-blog:import-demo',
    description: 'Creates content types and demo content for the ibexa-clean-blog theme.',
)]
class CleanBlogDemoContentCommand extends Command
{
    private Repository $repository;
    private \Netgen\TagsBundle\API\Repository\TagsService $tagsService;
    private string $bundleDir;

    public function __construct(Repository $repository, \Netgen\TagsBundle\API\Repository\TagsService $tagsService, string $bundleDir)
    {
        parent::__construct();
        $this->repository = $repository;
        $this->tagsService = $tagsService;
        $this->bundleDir = $bundleDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Admin user
        $adminUser = $this->repository->getUserService()->loadUser(14);
        $this->repository->getPermissionResolver()->setCurrentUserReference($adminUser);

        $io->title('Ibexa Clean Blog — Demo Import');

        // Create Content Types
        $folderType = $this->ensureFolderContentType($io);
        $categoryType = $this->ensureCategoryContentType($io);
        $authorType = $this->ensureAuthorContentType($io);
        $postType = $this->ensureBlogPostContentType($io);
        $pageType = $this->ensurePageContentType($io);
        $settingsType = $this->ensureSettingsContentType($io);

        $homeId = $this->getHomepageLocationId();

        // Cleanup existing demo content
        $this->cleanUpDemoContent($io, $homeId);

        // Create Content
        $io->section('Creating Content');

        // Update root to be homepage (folder)
        $this->ensureRootIsHomepage($io);

        // Update Admin User Bio
        $this->updateAdminUser($io);

        // Demo Users
        $authorIds = $this->ensureDemoUsersExist($io);

        // Frontend Listing Folders
        $folderLocationIds = $this->ensureFrontendFoldersExist($io, $folderType, $homeId);

        // Categories
        $categoriesLocationId = $folderLocationIds['aanwik_folder_categories'] ?? $homeId;
        $categories = $this->ensureCategoriesExist($io, $categoryType, $categoriesLocationId);

        // Tags
        $tags = $this->ensureTagsExist($io);

        // Blog Posts
        $blogLocationId = $folderLocationIds['aanwik_folder_blog'] ?? $homeId;
        $this->ensureBlogPostsExist($io, $postType, $blogLocationId, $authorIds, $categories, $tags);

        // Standalone Pages
        $this->ensurePagesExist($io, $pageType, $homeId);

        // Global Settings
        $this->ensureSettingsExist($io, $settingsType);

        $io->success('Demo content imported successfully.');

        return Command::SUCCESS;
    }

    // =========================================================================
    // Content Types
    // =========================================================================

    private function ensureFolderContentType(SymfonyStyle $io): ContentType
    {
        $contentTypeService = $this->repository->getContentTypeService();
        $ct = $contentTypeService->loadContentTypeByIdentifier('folder');
        return $this->ensureMenuFields($io, $ct, $contentTypeService);
    }

    private function ensureBlogPostContentType(SymfonyStyle $io): ContentType
    {
        $contentTypeService = $this->repository->getContentTypeService();

        try {
            $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_post');
            
            // Check for missing fields and add them (Categories, Author relation, Tags)
            $missingFields = [];
            if ($ct->getFieldDefinition('categories') === null) $missingFields[] = 'categories';
            if ($ct->getFieldDefinition('post_author') === null) $missingFields[] = 'post_author';
            if ($ct->getFieldDefinition('tags') === null) $missingFields[] = 'tags';

            if (!empty($missingFields)) {
                $io->note('Updating "aanwik_clean_blog_post" with missing fields: ' . implode(', ', $missingFields));
                $draft = $contentTypeService->createContentTypeDraft($ct);
                
                if (in_array('categories', $missingFields)) {
                    $field = $contentTypeService->newFieldDefinitionCreateStruct('categories', 'ibexa_object_relation_list');
                    $field->names = ['eng-GB' => 'Categories'];
                    $field->position = 180;
                    $contentTypeService->addFieldDefinition($draft, $field);
                }

                if (in_array('post_author', $missingFields)) {
                    $field = $contentTypeService->newFieldDefinitionCreateStruct('post_author', 'ibexa_object_relation');
                    $field->names = ['eng-GB' => 'Author Relation'];
                    $field->position = 190;
                    $contentTypeService->addFieldDefinition($draft, $field);
                }

                if (in_array('tags', $missingFields)) {
                    $field = $contentTypeService->newFieldDefinitionCreateStruct('tags', 'eztags');
                    $field->names = ['eng-GB' => 'Tags'];
                    $field->position = 200;
                    $contentTypeService->addFieldDefinition($draft, $field);
                }

                $contentTypeService->publishContentTypeDraft($draft);
                return $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_post');
            }

            return $ct;
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException $e) {
            $io->note('Creating "aanwik_clean_blog_post" content type...');

            $contentTypeGroup = $contentTypeService->loadContentTypeGroupByIdentifier('Content');
            $createStruct = $contentTypeService->newContentTypeCreateStruct('aanwik_clean_blog_post');
            $createStruct->mainLanguageCode = 'eng-GB';
            $createStruct->nameSchema = '<title>';
            $createStruct->urlAliasSchema = '<title>';
            $createStruct->names = ['eng-GB' => 'Clean Blog Post'];

            $pos = 10;
            $field = $contentTypeService->newFieldDefinitionCreateStruct('title', 'ibexa_string');
            $field->names = ['eng-GB' => 'Title'];
            $field->isRequired = true;
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('subtitle', 'ibexa_string');
            $field->names = ['eng-GB' => 'Subtitle'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('author', 'ibexa_string');
            $field->names = ['eng-GB' => 'Author (Legacy Text)'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('date', 'ibexa_string');
            $field->names = ['eng-GB' => 'Date'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('excerpt', 'ibexa_text');
            $field->names = ['eng-GB' => 'Excerpt'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('content', 'ibexa_richtext');
            $field->names = ['eng-GB' => 'Content'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('image', 'ibexa_image');
            $field->names = ['eng-GB' => 'Featured Image'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            // New fields
            $field = $contentTypeService->newFieldDefinitionCreateStruct('categories', 'ibexa_object_relation_list');
            $field->names = ['eng-GB' => 'Categories'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('post_author', 'ibexa_object_relation');
            $field->names = ['eng-GB' => 'Author Relation'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('tags', 'eztags');
            $field->names = ['eng-GB' => 'Tags'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $draft = $contentTypeService->createContentType($createStruct, [$contentTypeGroup]);
            $contentTypeService->publishContentTypeDraft($draft);
            $io->success('Blog Post content type created.');
            return $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_post');
        }
    }

    private function ensureCategoryContentType(SymfonyStyle $io): ContentType
    {
        $contentTypeService = $this->repository->getContentTypeService();

        try {
            return $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_category');
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException $e) {
            $io->note('Creating "aanwik_clean_blog_category" content type...');

            $contentTypeGroup = $contentTypeService->loadContentTypeGroupByIdentifier('Content');
            $createStruct = $contentTypeService->newContentTypeCreateStruct('aanwik_clean_blog_category');
            $createStruct->mainLanguageCode = 'eng-GB';
            $createStruct->nameSchema = '<name>';
            $createStruct->urlAliasSchema = '<name>';
            $createStruct->names = ['eng-GB' => 'Clean Blog Category'];
            $createStruct->isContainer = true;

            $pos = 10;
            $field = $contentTypeService->newFieldDefinitionCreateStruct('name', 'ibexa_string');
            $field->names = ['eng-GB' => 'Name'];
            $field->isRequired = true;
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('description', 'ibexa_text');
            $field->names = ['eng-GB' => 'Description'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('hero_image', 'ibexa_image');
            $field->names = ['eng-GB' => 'Hero Image'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $draft = $contentTypeService->createContentType($createStruct, [$contentTypeGroup]);
            $contentTypeService->publishContentTypeDraft($draft);
            $io->success('Category content type created.');
            $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_category');
            return $this->ensureMenuFields($io, $ct, $contentTypeService);
        }
    }

    private function ensureAuthorContentType(SymfonyStyle $io): ContentType
    {
        $contentTypeService = $this->repository->getContentTypeService();

        try {
            $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_author');
            return $this->ensureMenuFields($io, $ct, $contentTypeService);
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException $e) {
            $io->note('Creating "aanwik_clean_blog_author" content type...');

            $contentTypeGroup = $contentTypeService->loadContentTypeGroupByIdentifier('Content');
            $createStruct = $contentTypeService->newContentTypeCreateStruct('aanwik_clean_blog_author');
            $createStruct->mainLanguageCode = 'eng-GB';
            $createStruct->nameSchema = '<name>';
            $createStruct->urlAliasSchema = '<name>';
            $createStruct->names = ['eng-GB' => 'Clean Blog Author'];

            $pos = 10;
            $field = $contentTypeService->newFieldDefinitionCreateStruct('name', 'ibexa_string');
            $field->names = ['eng-GB' => 'Name'];
            $field->isRequired = true;
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('bio', 'ibexa_text');
            $field->names = ['eng-GB' => 'Bio'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('image', 'ibexa_image');
            $field->names = ['eng-GB' => 'Profile Image'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('facebook', 'ibexa_string');
            $field->names = ['eng-GB' => 'Facebook URL'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('twitter', 'ibexa_string');
            $field->names = ['eng-GB' => 'Twitter/X URL'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('github', 'ibexa_string');
            $field->names = ['eng-GB' => 'GitHub URL'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('linkedin', 'ibexa_string');
            $field->names = ['eng-GB' => 'LinkedIn URL'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('user', 'ibexa_object_relation');
            $field->names = ['eng-GB' => 'System User Relation'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $draft = $contentTypeService->createContentType($createStruct, [$contentTypeGroup]);
            $contentTypeService->publishContentTypeDraft($draft);
            $io->success('Author content type created.');
            $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_author');
            return $this->ensureMenuFields($io, $ct, $contentTypeService);
        }
    }

    private function ensureUserContentType(SymfonyStyle $io): void
    {
        $contentTypeService = $this->repository->getContentTypeService();
        $ct = $contentTypeService->loadContentTypeByIdentifier('user');

        $fieldsToMigrate = [
            'bio' => ['name' => 'Biography', 'type' => 'ibexa_text', 'pos' => 150],
            'facebook' => ['name' => 'Facebook', 'type' => 'ibexa_string', 'pos' => 160],
            'twitter' => ['name' => 'Twitter/X', 'type' => 'ibexa_string', 'pos' => 170],
            'linkedin' => ['name' => 'LinkedIn', 'type' => 'ibexa_string', 'pos' => 180],
            'github' => ['name' => 'GitHub', 'type' => 'ibexa_string', 'pos' => 190],
            'instagram' => ['name' => 'Instagram', 'type' => 'ibexa_string', 'pos' => 200],
        ];

        $updated = false;
        foreach ($fieldsToMigrate as $identifier => $info) {
            if ($ct->getFieldDefinition($identifier) === null) {
                $io->note("Adding \"$identifier\" field to user content type...");
                $draft = $contentTypeService->createContentTypeDraft($ct);
                $field = $contentTypeService->newFieldDefinitionCreateStruct($identifier, $info['type']);
                $field->names = ['eng-GB' => $info['name']];
                $field->position = $info['pos'];
                $contentTypeService->addFieldDefinition($draft, $field);
                $contentTypeService->publishContentTypeDraft($draft);
                $ct = $contentTypeService->loadContentTypeByIdentifier('user');
                $updated = true;
            }
        }

        $this->ensureMenuFields($io, $ct, $contentTypeService);
    }

    private function ensurePageContentType(SymfonyStyle $io): ContentType
    {
        $contentTypeService = $this->repository->getContentTypeService();

        try {
            $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_page');
            return $this->ensureMenuFields($io, $ct, $contentTypeService);
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException $e) {
            $io->note('Creating "aanwik_clean_blog_page" content type...');

            $contentTypeGroup = $contentTypeService->loadContentTypeGroupByIdentifier('Content');
            $createStruct = $contentTypeService->newContentTypeCreateStruct('aanwik_clean_blog_page');
            $createStruct->mainLanguageCode = 'eng-GB';
            $createStruct->nameSchema = '<title>';
            $createStruct->urlAliasSchema = '<title>';
            $createStruct->names = ['eng-GB' => 'Clean Blog Page'];

            $pos = 10;
            $field = $contentTypeService->newFieldDefinitionCreateStruct('title', 'ibexa_string');
            $field->names = ['eng-GB' => 'Title'];
            $field->isRequired = true;
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('subtitle', 'ibexa_string');
            $field->names = ['eng-GB' => 'Subtitle'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('body', 'ibexa_richtext');
            $field->names = ['eng-GB' => 'Body'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('hero_image', 'ibexa_image');
            $field->names = ['eng-GB' => 'Hero Background Image'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $draft = $contentTypeService->createContentType($createStruct, [$contentTypeGroup]);
            $contentTypeService->publishContentTypeDraft($draft);
            $io->success('Page content type created.');
            $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_page');
            return $this->ensureMenuFields($io, $ct, $contentTypeService);
        }
    }

    private function ensureSettingsContentType(SymfonyStyle $io): ContentType
    {
        $contentTypeService = $this->repository->getContentTypeService();

        try {
            $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_settings');

            // Migration: check each field individually and update
            $fieldsToMigrate = [
                'disqus_shortname' => ['name' => 'Disqus Shortname', 'type' => 'ibexa_string', 'pos' => 140],
                'posts_per_page' => ['name' => 'Blog Posts Per Page', 'type' => 'ibexa_string', 'pos' => 130],
            ];

            $updated = false;
            foreach ($fieldsToMigrate as $identifier => $info) {
                if ($ct->getFieldDefinition($identifier) === null) {
                    $io->note("Adding \"$identifier\" field to existing settings content type...");
                    $draft = $contentTypeService->createContentTypeDraft($ct);
                    $field = $contentTypeService->newFieldDefinitionCreateStruct($identifier, $info['type']);
                    $field->names = ['eng-GB' => $info['name']];
                    $field->position = $info['pos'];
                    $contentTypeService->addFieldDefinition($draft, $field);
                    $contentTypeService->publishContentTypeDraft($draft);
                    
                    // Reload CT after modification
                    $ct = $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_settings');
                    $updated = true;
                    $io->success("Added $identifier field.");
                }
            }

            return $ct;
        } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException $e) {
            $io->note('Creating "aanwik_clean_blog_settings" content type...');

            $contentTypeGroup = $contentTypeService->loadContentTypeGroupByIdentifier('Content');
            $createStruct = $contentTypeService->newContentTypeCreateStruct('aanwik_clean_blog_settings');
            $createStruct->mainLanguageCode = 'eng-GB';
            $createStruct->nameSchema = '<title>';
            $createStruct->urlAliasSchema = '<title>';
            $createStruct->names = ['eng-GB' => 'Clean Blog Settings'];

            $pos = 10;
            $field = $contentTypeService->newFieldDefinitionCreateStruct('title', 'ibexa_string');
            $field->names = ['eng-GB' => 'Title'];
            $field->isRequired = true;
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('email', 'ibexa_string');
            $field->names = ['eng-GB' => 'Email Address'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('phone_number', 'ibexa_string');
            $field->names = ['eng-GB' => 'Phone Number'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('address', 'ibexa_text');
            $field->names = ['eng-GB' => 'Address'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('fb_link', 'ibexa_string');
            $field->names = ['eng-GB' => 'Facebook Link'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('tw_link', 'ibexa_string');
            $field->names = ['eng-GB' => 'Twitter / X Link'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('ig_link', 'ibexa_string');
            $field->names = ['eng-GB' => 'Instagram Link'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('gh_link', 'ibexa_string');
            $field->names = ['eng-GB' => 'GitHub Link'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('li_link', 'ibexa_string');
            $field->names = ['eng-GB' => 'LinkedIn Link'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('copyright', 'ibexa_string');
            $field->names = ['eng-GB' => 'Copyright Text'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('footer_text', 'ibexa_text');
            $field->names = ['eng-GB' => 'Footer Text'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('posts_per_page', 'ibexa_string');
            $field->names = ['eng-GB' => 'Blog Posts Per Page'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $field = $contentTypeService->newFieldDefinitionCreateStruct('disqus_shortname', 'ibexa_string');
            $field->names = ['eng-GB' => 'Disqus Shortname'];
            $field->position = $pos += 10;
            $createStruct->addFieldDefinition($field);

            $draft = $contentTypeService->createContentType($createStruct, [$contentTypeGroup]);
            $contentTypeService->publishContentTypeDraft($draft);
            $io->success('Settings content type created.');
            return $contentTypeService->loadContentTypeByIdentifier('aanwik_clean_blog_settings');
        }
    }

    // =========================================================================
    // Demo Content
    // =========================================================================

    private function ensureRootIsHomepage(SymfonyStyle $io): void
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        try {
            $contentService->loadContentInfoByRemoteId('clean_blog_homepage_1');
            return;
        } catch (\Exception $e) {}

        // Update existing root content at location 2
        try {
            $rootLocation = $locationService->loadLocation(2);
            $existingContent = $contentService->loadContent($rootLocation->contentInfo->id);

            // If root is not our type, create a new folder as child of root or update root
            if ($existingContent->getContentType()->identifier !== 'folder') {
                $folderType = $contentTypeService->loadContentTypeByIdentifier('folder');
                $struct = $contentService->newContentCreateStruct($folderType, 'eng-GB');
                $struct->remoteId = 'clean_blog_homepage_1';
                $struct->setField('name', 'Clean Blog');
                $struct->setField('short_name', 'A Blog Theme by Start Bootstrap');

                $imagePath = $this->bundleDir . '/src/Resources/public/images/home-bg.jpg';
                if (file_exists($imagePath)) {
                    $imageValue = ImageValue::fromString($imagePath);
                    $imageValue->alternativeText = 'Home background';
                    $struct->setField('hero_image', $imageValue);
                }

                $locStruct = $locationService->newLocationCreateStruct(2);
                $draft = $contentService->createContent($struct, [$locStruct]);
                $contentService->publishVersion($draft->versionInfo);
                $io->success('Homepage content created.');
            }
        } catch (\Exception $e) {
            $io->warning('Could not update root: ' . $e->getMessage());
        }
    }

    private function updateAdminUser(SymfonyStyle $io): void
    {
        $userService = $this->repository->getUserService();
        $contentService = $this->repository->getContentService();

        try {
            $user = $userService->loadUser(14);
            $content = $contentService->loadContent($user->contentInfo->id);

            $updateStruct = $contentService->newContentUpdateStruct();
            $updateStruct->setField('bio', 'Lead developer at Aanwik, passionate about clean code and modern web architectures. Exploring the limits of Ibexa DXP.');
            $updateStruct->setField('github', 'https://github.com/aanwik');
            $updateStruct->setField('twitter', 'https://twitter.com/aanwik');

            $draft = $contentService->createContentDraft($content->contentInfo);
            $contentService->updateContent($draft->versionInfo, $updateStruct);
            $contentService->publishVersion($draft->versionInfo);

            $io->success('Admin user bio updated.');
        } catch (\Exception $e) {
            $io->warning('Could not update admin user bio: ' . $e->getMessage());
        }
    }

    private function ensureDemoUsersExist(SymfonyStyle $io): array
    {
        return $this->repository->sudo(function () use ($io) {
            $userService = $this->repository->getUserService();
            $authorIds = [14]; // Always include Admin

            $demoUsers = [
                [
                    'login' => 'demo_author',
                    'email' => 'author@cleanblog.com',
                    'password' => 'DemoAuthor@123',
                    'first_name' => 'John',
                    'last_name' => 'Explorer',
                    'bio' => 'Adventurous writer traveling the world and reporting back on the most interesting scientific discoveries.',
                    'github' => 'https://github.com/explorer',
                    'twitter' => 'https://twitter.com/explorer',
                ]
            ];

            // Try common user group IDs
            $userGroup = null;
            foreach ([4, 12, 5] as $groupId) {
                try {
                    $userGroup = $userService->loadUserGroup($groupId);
                    break;
                } catch (\Exception $e) {}
            }

            if (!$userGroup) {
                $io->warning('Could not find a valid user group to assign demo users. Using ID 14 (Admin) for authors.');
                return $authorIds;
            }

                foreach ($demoUsers as $userData) {
                try {
                    $user = $userService->loadUserByLogin($userData['login']);
                    $authorIds[] = $user->contentInfo->id;
                    $io->note('User "' . $userData['login'] . '" already exists.');
                } catch (\Exception $e) {
                    try {
                        $struct = $userService->newUserCreateStruct($userData['login'], $userData['email'], $userData['password'], 'eng-GB');
                        $struct->setField('first_name', $userData['first_name']);
                        $struct->setField('last_name', $userData['last_name']);
                        $struct->setField('bio', $userData['bio']);
                        if (isset($userData['github'])) $struct->setField('github', $userData['github']);
                        if (isset($userData['twitter'])) $struct->setField('twitter', $userData['twitter']);

                        $user = $userService->createUser($struct, [$userGroup]);
                        $authorIds[] = $user->contentInfo->id;
                        $io->success('Demo user created: ' . $userData['login']);
                    } catch (\Ibexa\Contracts\Core\Repository\Exceptions\ContentValidationException $ex) {
                        $io->error('Validation error creating user "' . $userData['login'] . '": ' . $ex->getMessage());
                        throw $ex;
                    } catch (\Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException $ex) {
                        $io->error('Field validation error creating user "' . $userData['login'] . '": ' . $ex->getMessage());
                        foreach ($ex->getFieldErrors() as $fieldId => $fieldErrors) {
                            foreach ($fieldErrors as $fieldError) {
                                // In some versions it's an array, in others an object
                                $msg = is_array($fieldError) ? ($fieldError['errorMessage'] ?? 'Unknown error') : ($fieldError->errorMessage ?? 'Unknown error');
                                $io->note('Field error: ' . $fieldId . ' - ' . $msg);
                            }
                        }
                        throw $ex;
                    } catch (\Exception $ex) {
                        $io->error('Error creating user "' . $userData['login'] . '": ' . $ex->getMessage());
                        throw $ex;
                    }
                }
            }

            return $authorIds;
        });
    }

    private function ensureAuthorsExist(SymfonyStyle $io, ContentType $ct): array
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $authorIds = [];

        $authors = [
            [
                'name' => 'Kinjal Goswami',
                'bio' => 'Lead developer and Ibexa DXP expert.',
                'facebook' => 'https://facebook.com',
                'twitter' => 'https://twitter.com',
                'github' => 'https://github.com',
                'linkedin' => 'https://linkedin.com',
                'remoteId' => 'clean_blog_author_1',
            ],
            [
                'name' => 'Jane Doe',
                'bio' => 'Tech enthusiast and lifestyle blogger.',
                'facebook' => 'https://facebook.com',
                'twitter' => 'https://twitter.com',
                'remoteId' => 'clean_blog_author_2',
            ]
        ];

        foreach ($authors as $author) {
            try {
                $authorIds[] = $contentService->loadContentByRemoteId($author['remoteId'])->id;
            } catch (\Exception $e) {
                $struct = $contentService->newContentCreateStruct($ct, 'eng-GB');
                $struct->remoteId = $author['remoteId'];
                $struct->setField('name', $author['name']);
                $struct->setField('bio', $author['bio']);
                $struct->setField('facebook', $author['facebook'] ?? '');
                $struct->setField('twitter', $author['twitter'] ?? '');
                $struct->setField('github', $author['github'] ?? '');
                $struct->setField('linkedin', $author['linkedin'] ?? '');

                $locStruct = $locationService->newLocationCreateStruct(2);
                $draft = $contentService->createContent($struct, [$locStruct]);
                $contentService->publishVersion($draft->versionInfo);
                $authorIds[] = $draft->contentInfo->id;
                $io->success('Author created: ' . $author['name']);
            }
        }

        return $authorIds;
    }

    private function ensureTagsExist(SymfonyStyle $io): array
    {
        $tags = ['Ibexa', 'PHP', 'Symfony', 'Web Design', 'Technology'];
        $tagObjects = [];
        
        foreach ($tags as $keyword) {
            try {
                // Try to find existing tag
                $existingTags = $this->tagsService->loadTagsByKeyword($keyword, 'eng-GB');
                
                if ($existingTags->count() > 0) {
                    $tagObjects[] = $existingTags->first();
                    continue;
                }
                
                // Create new tag at root (parent ID 0)
                $createStruct = $this->tagsService->newTagCreateStruct(0, 'eng-GB');
                $createStruct->setKeyword($keyword, 'eng-GB');
                $tag = $this->tagsService->createTag($createStruct);
                $tagObjects[] = $tag;
                $io->success('Tag created: ' . $keyword);
            } catch (\Exception $e) {
                $io->warning('Could not create tag "' . $keyword . '": ' . $e->getMessage());
            }
        }
        
        return $tagObjects;
    }

    private function ensureFrontendFoldersExist(SymfonyStyle $io, ContentType $ct, int $parentLocationId): array
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $folderLocations = [];

        $folders = [
            'aanwik_folder_blog' => 'Blog',
            'aanwik_folder_categories' => 'Categories',
            'aanwik_folder_tags' => 'Tags',
            'aanwik_folder_authors' => 'Authors',
            'aanwik_folder_archives' => 'Archives',
        ];

        foreach ($folders as $remoteId => $name) {
            try {
                $content = $contentService->loadContentByRemoteId($remoteId);
                $folderLocations[$remoteId] = $content->contentInfo->mainLocationId;
            } catch (\Exception $e) {
                $struct = $contentService->newContentCreateStruct($ct, 'eng-GB');
                $struct->remoteId = $remoteId;
                $struct->setField('name', $name);
                $struct->setField('show_in_header_menu', true);
                if ($remoteId === 'aanwik_folder_archives') {
                    $struct->setField('show_in_footer_menu', true);
                }

                $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
                $draft = $contentService->createContent($struct, [$locStruct]);
                $contentService->publishVersion($draft->versionInfo);
                $folderLocations[$remoteId] = $draft->contentInfo->mainLocationId;
                $io->success("Frontend folder created: $name");
            }
        }
        return $folderLocations;
    }

    private function ensureCategoriesExist(SymfonyStyle $io, ContentType $ct, int $parentLocationId): array
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $categoryIds = [];

        $categories = [
            ['name' => 'Space', 'remoteId' => 'clean_blog_category_space'],
            ['name' => 'Science', 'remoteId' => 'clean_blog_category_science'],
            ['name' => 'Exploration', 'remoteId' => 'clean_blog_category_exploration'],
            ['name' => 'Future', 'remoteId' => 'clean_blog_category_future'],
        ];

        foreach ($categories as $cat) {
            try {
                $categoryIds[] = $contentService->loadContentByRemoteId($cat['remoteId'])->id;
            } catch (\Exception $e) {
                $struct = $contentService->newContentCreateStruct($ct, 'eng-GB');
                $struct->remoteId = $cat['remoteId'];
                $struct->setField('name', $cat['name']);
                $struct->setField('description', 'Posts related to ' . $cat['name']);

                $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
                $draft = $contentService->createContent($struct, [$locStruct]);
                $contentService->publishVersion($draft->versionInfo);
                $categoryIds[] = $draft->contentInfo->id;
                $io->success('Category created: ' . $cat['name']);
            }
        }

        return $categoryIds;
    }

    private function ensureBlogPostsExist(SymfonyStyle $io, ContentType $ct, int $parentLocationId, array $authorIds, array $categoryIds, array $tagObjects): void
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        $posts = [
            [
                'title' => 'Man must explore, and this is exploration at its greatest',
                'subtitle' => 'Problems look mighty small from 150 miles up',
                'author' => 'Start Bootstrap',
                'date' => 'September 24, 2023',
                'excerpt' => 'Never in all their history have men been able truly to conceive of the world as one: a single sphere, a globe, having the qualities of a globe.',
                'content' => '<p>Never in all their history have men been able truly to conceive of the world as one: a single sphere, a globe, having the qualities of a globe, a round earth in which all the directions eventually meet, in which there is no center because every point, or none, is center — an equal earth which all men occupy as equals. The airman\'s earth, if free men make it, will be truly round: a globe in practice, not in theory.</p><p>Science cuts two ways, of course; its products can be used for both good and evil. But there\'s no turning back from science. The early warnings about technological dangers also come from science.</p><p>What was most significant about the lunar voyage was not that man set foot on the Moon but that they set eye on the earth.</p><p>A Chinese tale tells of some men sent to harm a young girl who, upon seeing her beauty, become her protectors rather than her violators. That\'s how I felt seeing the Earth for the first time. I could not help but love and cherish her.</p><p>For those who have seen the Earth from space, and for the hundreds and perhaps thousands more who will, the experience most certainly changes your perspective. The things that we share in our world are far more valuable than those which divide us.</p><h2 class="section-heading">The Final Frontier</h2><p>There can be no thought of finishing for \'aiming for the stars.\' Both figuratively and literally, it is a task to occupy the generations. And no matter how much progress one makes, there is always the thrill of just beginning.</p><blockquote class="blockquote">The dreams of yesterday are the hopes of today and the reality of tomorrow. Science has not yet mastered prophecy. We predict too much for the next year and yet far too little for the next ten.</blockquote><p>Spaceflights cannot be stopped. This is not the work of any one man or even a group of men. It is a historical process which mankind is carrying out in accordance with the natural laws of human development.</p><h2 class="section-heading">Reaching for the Stars</h2><p>As we got further and further away, it [the Earth] diminished in size. Finally it shrank to the size of a marble, the most beautiful you can imagine. That beautiful, warm, living object looked so fragile, so delicate, that if you touched it with a finger it would crumble and fall apart. Seeing this has to change a man.</p><p>Space, the final frontier. These are the voyages of the Starship Enterprise. Its five-year mission: to explore strange new worlds, to seek out new life and new civilizations, to boldly go where no man has gone before.</p><p>As I stand out here in the wonders of the unknown at Hadley, I sort of realize there\'s a fundamental truth to our nature, Man must explore, and this is exploration at its greatest.</p>',
                'id' => 'clean_blog_post_1',
                'image' => 'post-bg.jpg',
                'categories' => [$categoryIds[0], $categoryIds[2]], // Space, Exploration
                'author_rel' => $authorIds[0],
                'tags' => isset($tagObjects[0]) ? [$tagObjects[0], $tagObjects[4]] : [], // Ibexa, Technology
            ],
            [
                'title' => 'I believe every human has a finite number of heartbeats. I don\'t intend to waste any of mine.',
                'subtitle' => '',
                'author' => 'Start Bootstrap',
                'date' => 'September 18, 2023',
                'excerpt' => 'Every human has a finite number of heartbeats. Make each one count.',
                'content' => '<p>Every human has a finite number of heartbeats. I don\'t intend to waste any of mine. This philosophy has guided explorers, scientists, and dreamers for centuries.</p><p>The pursuit of knowledge and the desire to push beyond our known boundaries is what makes us uniquely human. Whether we\'re exploring the depths of the ocean or the vastness of space, our heartbeats mark the rhythm of discovery.</p><p>In the grand tapestry of human achievement, each heartbeat represents a moment of potential — a chance to learn, to grow, to make a difference in the world around us.</p>',
                'id' => 'clean_blog_post_2',
                'image' => 'post-bg.jpg',
                'categories' => [$categoryIds[1]], // Science
                'author_rel' => isset($authorIds[1]) ? $authorIds[1] : $authorIds[0],
                'tags' => isset($tagObjects[1]) ? [$tagObjects[1], $tagObjects[2]] : [], // PHP, Symfony
            ],
            [
                'title' => 'Science has not yet mastered prophecy',
                'subtitle' => 'We predict too much for the next year and yet far too little for the next ten.',
                'author' => 'Start Bootstrap',
                'date' => 'August 24, 2023',
                'excerpt' => 'We predict too much for the next year and yet far too little for the next ten.',
                'content' => '<p>Science has not yet mastered prophecy. We predict too much for the next year and yet far too little for the next ten. The challenge of prediction lies not in our tools but in our understanding of the complex systems that govern our world.</p><p>From weather patterns to economic trends, from technological advances to social movements, the future remains stubbornly resistant to our attempts at forecasting. Yet this uncertainty is precisely what makes the pursuit of knowledge so exciting.</p><p>As we stand on the threshold of new discoveries, we must remember that the greatest breakthroughs often come from the most unexpected places. The future belongs to those who dare to dream and those who have the courage to pursue those dreams.</p>',
                'id' => 'clean_blog_post_3',
                'image' => 'post-bg.jpg',
                'categories' => [$categoryIds[1], $categoryIds[3]], // Science, Future
                'author_rel' => $authorIds[0],
                'tags' => isset($tagObjects[3]) ? [$tagObjects[3], $tagObjects[4]] : [], // Web Design, Technology
            ],
            [
                'title' => 'Failure is not an option',
                'subtitle' => 'Many say exploration is part of our destiny, but it\'s actually our duty to future generations.',
                'author' => 'Start Bootstrap',
                'date' => 'July 8, 2023',
                'excerpt' => 'Many say exploration is part of our destiny, but it\'s actually our duty to future generations.',
                'content' => '<p>Failure is not an option. Many say exploration is part of our destiny, but it\'s actually our duty to future generations and their quest to ensure the survival of the human species.</p><p>Throughout history, the greatest achievements of humanity have come from those who refused to accept failure as an outcome. From the Wright brothers\' first flight to the moon landings, from the development of vaccines to the creation of the internet — each breakthrough was born from an unwavering determination to succeed.</p><p>As we look to the future, we carry with us the lessons of the past. We know that the road ahead will be challenging, but we also know that it is precisely these challenges that will define us as a civilization.</p>',
                'id' => 'clean_blog_post_4',
                'image' => 'post-bg.jpg',
                'categories' => [$categoryIds[2]], // Exploration
                'author_rel' => isset($authorIds[1]) ? $authorIds[1] : $authorIds[0],
                'tags' => isset($tagObjects[0]) ? [$tagObjects[0], $tagObjects[2]] : [], // Ibexa, Symfony
            ],
        ];

        foreach ($posts as $post) {
            try {
                $postContent = $contentService->loadContentByRemoteId($post['id']);
                $io->note('Blog post "' . $post['title'] . '" already exists, updating specific fields.');
                // Update author just in case
                if (isset($post['author_rel'])) {
                    $this->repository->sudo(function () use ($contentService, $postContent, $post) {
                        $updateStruct = $contentService->newContentUpdateStruct();
                        $updateStruct->setField('post_author', $post['author_rel']);
                        $draft = $contentService->createContentDraft($postContent->contentInfo);
                        $contentService->updateContent($draft->versionInfo, $updateStruct);
                        $contentService->publishVersion($draft->versionInfo);
                    });
                }
            } catch (\Exception $e) {
                $struct = $contentService->newContentCreateStruct($ct, 'eng-GB');
                $struct->remoteId = $post['id'];
                $struct->setField('title', $post['title']);
                $struct->setField('subtitle', $post['subtitle']);
                $struct->setField('author', $post['author']);
                $struct->setField('date', $post['date']);
                $struct->setField('excerpt', $post['excerpt']);
                $struct->setField('content', $this->wrapRichText($post['content']));
                
                // Set Relations
                $struct->setField('categories', $post['categories']);
                $struct->setField('post_author', $post['author_rel']);
                if (!empty($post['tags'])) {
                    $struct->setField('tags', $post['tags']);
                }

                if (isset($post['image'])) {
                    $imagePath = $this->bundleDir . '/src/Resources/public/images/' . $post['image'];
                    if (file_exists($imagePath)) {
                        $imageValue = ImageValue::fromString($imagePath);
                        $imageValue->alternativeText = $post['title'];
                        $struct->setField('image', $imageValue);
                    }
                }

                $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
                $draft = $contentService->createContent($struct, [$locStruct]);
                $contentService->publishVersion($draft->versionInfo);
                $io->success('Blog post created: ' . $post['title']);
            }
        }
    }

    private function ensurePagesExist(SymfonyStyle $io, ContentType $ct, int $parentLocationId): void
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        $pages = [
            [
                'title' => 'About Me',
                'subtitle' => 'This is what I do.',
                'body' => '<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Saepe nostrum ullam eveniet pariatur voluptates odit, fuga atque ea nobis sit soluta odio, adipisci quas excepturi maxime quae totam ducimus consectetur?</p><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Eius praesentium recusandae illo eaque architecto error, repellendus iusto reprehenderit, doloribus, minus sunt. Numquam at quae voluptatum in officia voluptas voluptatibus, minus!</p><p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aut consequuntur magnam, excepturi aliquid ex itaque esse est vero natus quae optio aperiam soluta voluptatibus corporis atque iste neque sit tempora!</p>',
                'id' => 'clean_blog_page_about',
                'image' => 'about-bg.jpg',
            ],
            [
                'title' => 'Contact Me',
                'subtitle' => 'Have questions? I have answers.',
                'body' => '<p>Want to get in touch? Fill out the form below to send me a message and I will get back to you as soon as possible!</p>',
                'id' => 'clean_blog_page_contact',
                'image' => 'contact-bg.jpg',
            ],
            [
                'title' => 'Privacy Policy',
                'subtitle' => 'Your privacy is important to us.',
                'body' => '<p>This privacy policy explains how we collect, use, and protect your personal information.</p><p>We do not sell your data to third parties. We use cookies to improve your experience on our blog.</p>',
                'id' => 'clean_blog_page_privacy',
                'image' => 'about-bg.jpg',
            ],
            [
                'title' => 'Terms of Service',
                'subtitle' => 'Please read our terms of use.',
                'body' => '<p>By using this blog, you agree to comply with our terms and conditions.</p><p>Content on this site is for informational purposes only. You may not reproduce content without permission.</p>',
                'id' => 'clean_blog_page_terms',
                'image' => 'about-bg.jpg',
            ],
        ];

        foreach ($pages as $page) {
            try {
                $contentService->loadContentByRemoteId($page['id']);
                $io->note('Page "' . $page['title'] . '" already exists, skipping.');
            } catch (\Exception $e) {
                $struct = $contentService->newContentCreateStruct($ct, 'eng-GB');
                $struct->remoteId = $page['id'];
                $struct->setField('title', $page['title']);
                $struct->setField('subtitle', $page['subtitle']);
                $struct->setField('body', $this->wrapRichText($page['body']));
                
                // Set Header and Footer Menu Flags as requested by User
                if (in_array($page['title'], ['About Me', 'Contact Me'])) {
                    $struct->setField('show_in_header_menu', true);
                }
                
                if (in_array($page['title'], ['Privacy Policy', 'Terms of Service'])) {
                    $struct->setField('show_in_footer_menu', true);
                }

                if (isset($page['image'])) {
                    $imagePath = $this->bundleDir . '/src/Resources/public/images/' . $page['image'];
                    if (file_exists($imagePath)) {
                        $imageValue = ImageValue::fromString($imagePath);
                        $imageValue->alternativeText = $page['title'];
                        $struct->setField('hero_image', $imageValue);
                    }
                }

                $locStruct = $locationService->newLocationCreateStruct($parentLocationId);
                $draft = $contentService->createContent($struct, [$locStruct]);
                $contentService->publishVersion($draft->versionInfo);
                $io->success('Page created: ' . $page['title']);
            }
        }
    }

    private function ensureSettingsExist(SymfonyStyle $io, ContentType $ct): void
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        try {
            $contentService->loadContentInfoByRemoteId('aanwik_clean_blog_settings_global');
            return;
        } catch (\Exception $e) {}

        $struct = $contentService->newContentCreateStruct($ct, 'eng-GB');
        $struct->remoteId = 'aanwik_clean_blog_settings_global';
        $struct->setField('title', 'Global Site Settings');
        $struct->setField('email', 'hello@cleanblog.com');
        $struct->setField('phone_number', '+1 (555) 123-4567');
        $struct->setField('address', "123 Blog Street\nNew York, NY 10001\nUnited States");
        $struct->setField('fb_link', 'https://facebook.com');
        $struct->setField('tw_link', 'https://twitter.com');
        $struct->setField('ig_link', 'https://instagram.com');
        $struct->setField('gh_link', 'https://github.com');
        $struct->setField('li_link', 'https://linkedin.com');
        $struct->setField('copyright', '© 2026 Clean Blog. All rights reserved.');
        $struct->setField('footer_text', 'A clean and beautiful blog built with Ibexa DXP.');
        $struct->setField('posts_per_page', '10');
        $struct->setField('disqus_shortname', 'clean-blog-demo');

        $locStruct = $locationService->newLocationCreateStruct(2);
        $draft = $contentService->createContent($struct, [$locStruct]);
        $contentService->publishVersion($draft->versionInfo);
        $io->success('Global settings created.');
    }

    // =========================================================================
    // Helpers
    // =========================================================================
    
    private function ensureMenuFields(SymfonyStyle $io, ContentType $ct, \Ibexa\Contracts\Core\Repository\ContentTypeService $contentTypeService): ContentType
    {
        $missingFields = [];
        if ($ct->getFieldDefinition('show_in_header_menu') === null) {
            $missingFields[] = 'show_in_header_menu';
        }
        if ($ct->getFieldDefinition('show_in_footer_menu') === null) {
            $missingFields[] = 'show_in_footer_menu';
        }

        if (!empty($missingFields)) {
            $io->note("Adding menu fields to " . $ct->identifier . "...");
            $draft = $contentTypeService->createContentTypeDraft($ct);

            if (in_array('show_in_header_menu', $missingFields)) {
                $field = $contentTypeService->newFieldDefinitionCreateStruct('show_in_header_menu', 'ibexa_boolean');
                $field->names = ['eng-GB' => 'Show in Header Menu'];
                $field->position = 300;
                $contentTypeService->addFieldDefinition($draft, $field);
            }

            if (in_array('show_in_footer_menu', $missingFields)) {
                $field = $contentTypeService->newFieldDefinitionCreateStruct('show_in_footer_menu', 'ibexa_boolean');
                $field->names = ['eng-GB' => 'Show in Footer Menu'];
                $field->position = 310;
                $contentTypeService->addFieldDefinition($draft, $field);
            }

            $contentTypeService->publishContentTypeDraft($draft);
            $io->success("Menu fields added to " . $ct->identifier . ".");
            return $contentTypeService->loadContentTypeByIdentifier($ct->identifier);
        }

        return $ct;
    }

    private function getHomepageLocationId(): int
    {
        return 2;
    }

    private function cleanUpDemoContent(SymfonyStyle $io, int $parentLocationId): void
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();

        $remoteIds = [
            'clean_blog_homepage_1',
            'clean_blog_post_1',
            'clean_blog_post_2',
            'clean_blog_post_3',
            'clean_blog_post_4',
            'clean_blog_page_about',
            'clean_blog_page_contact',
            'clean_blog_page_privacy',
            'clean_blog_page_terms',
            'aanwik_clean_blog_settings_global',
            'clean_blog_author_1',
            'clean_blog_author_2',
            'clean_blog_category_space',
            'clean_blog_category_science',
            'clean_blog_category_exploration',
            'clean_blog_category_future',
            'aanwik_folder_blog',
            'aanwik_folder_categories',
            'aanwik_folder_tags',
            'aanwik_folder_authors',
            'aanwik_folder_archives',
        ];

        foreach ($remoteIds as $remoteId) {
            try {
                $contentInfo = $contentService->loadContentInfoByRemoteId($remoteId);
                $contentService->deleteContent($contentInfo);
                $io->note("Deleted existing content: $remoteId");
            } catch (\Exception $e) {
                // Not found, nothing to delete
            }
        }
    }

    private function wrapRichText(string $html): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:ezxhtml="http://ibexa.co/xmlns/dxp/docbook/xhtml" xmlns:ezcustom="http://ibexa.co/xmlns/dxp/docbook/custom" version="5.0-variant ezpublish-1.0">
' . $this->htmlToDocbook($html) . '
</section>';
    }

    private function htmlToDocbook(string $html): string
    {
        // Simple HTML to DocBook conversion for common elements
        $result = $html;

        // Convert headings
        $result = preg_replace('/<h2[^>]*>(.*?)<\/h2>/s', '<title>$1</title>', $result);
        $result = preg_replace('/<h3[^>]*>(.*?)<\/h3>/s', '<title>$1</title>', $result);

        // Convert paragraphs
        $result = preg_replace('/<p>(.*?)<\/p>/s', '<para>$1</para>', $result);

        // Convert blockquotes
        $result = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/s', '<blockquote><para>$1</para></blockquote>', $result);

        // Convert bold/strong
        $result = preg_replace('/<(strong|b)>(.*?)<\/(strong|b)>/s', '<emphasis role="strong">$2</emphasis>', $result);

        // Convert italic/em
        $result = preg_replace('/<(em|i)>(.*?)<\/(em|i)>/s', '<emphasis>$2</emphasis>', $result);

        return $result;
    }
}
