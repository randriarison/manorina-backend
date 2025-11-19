<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'test:category-validation-and-slug',
    description: 'Test Category validation and slug generation (name > nameMg > ID)'
)]
class TestCategoryValidationAndSlugCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Category Validation and Slug Generation');
        
        // Get a test user
        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
        if (!$user) {
            $io->error('No user found. Please create a user first.');
            return Command::FAILURE;
        }
        
        $testCases = [
            [
                'description' => 'Valid Category with name only',
                'name' => 'Technology',
                'nameMg' => null,
                'categoryDescription' => 'Tech related topics',
                'descriptionMg' => null,
                'expectedValid' => true,
                'expectedSlugSource' => 'name'
            ],
            [
                'description' => 'Valid Category with nameMg only',
                'name' => '',
                'nameMg' => 'Teknolojia',
                'categoryDescription' => 'Tech topics in Malagasy',
                'descriptionMg' => null,
                'expectedValid' => true,
                'expectedSlugSource' => 'nameMg'
            ],
            [
                'description' => 'Valid Category with both name and nameMg (name should win)',
                'name' => 'Science',
                'nameMg' => 'Siansa',
                'categoryDescription' => 'Science topics',
                'descriptionMg' => 'Lohahevitra momba ny siansa',
                'expectedValid' => true,
                'expectedSlugSource' => 'name'
            ],
            [
                'description' => 'Valid Category with description only',
                'name' => 'Sports',
                'nameMg' => null,
                'categoryDescription' => null,
                'descriptionMg' => 'Fanatanjahan-tena',
                'expectedValid' => true,
                'expectedSlugSource' => 'name'
            ],
            [
                'description' => 'Invalid Category - no valid name fields',
                'name' => '',
                'nameMg' => '   ',
                'categoryDescription' => 'Some description',
                'descriptionMg' => null,
                'expectedValid' => false,
                'expectedSlugSource' => null
            ],
            [
                'description' => 'Invalid Category - no valid description fields',
                'name' => 'Music',
                'nameMg' => null,
                'categoryDescription' => '',
                'descriptionMg' => '   ',
                'expectedValid' => false,
                'expectedSlugSource' => null
            ],
            [
                'description' => 'Invalid Category - no valid fields at all',
                'name' => '   ',
                'nameMg' => '',
                'categoryDescription' => null,
                'descriptionMg' => '   ',
                'expectedValid' => false,
                'expectedSlugSource' => null
            ]
        ];
        
        $passedTests = 0;
        $totalTests = count($testCases);
        $createdCategories = [];
        
        foreach ($testCases as $index => $testCase) {
            $io->section("Test Case " . ($index + 1) . ": " . $testCase['description']);
            
            try {
                $category = new Category();
                $category->setAuthor($user);
                $category->setLevel(1); // Set a default level
                
                // Always set name (required field - cannot be null based on entity definition)
                $category->setName($testCase['name'] ?? '');
                
                if ($testCase['nameMg'] !== null) {
                    $category->setNameMg($testCase['nameMg']);
                }
                if ($testCase['categoryDescription'] !== null) {
                    $category->setDescription($testCase['categoryDescription']);
                }
                if ($testCase['descriptionMg'] !== null) {
                    $category->setDescriptionMg($testCase['descriptionMg']);
                }
                
                // Validate the category
                $violations = $this->validator->validate($category);
                $isValid = count($violations) === 0;
                
                $io->text("Name: '{$category->getName()}'");
                $io->text("NameMg: '{$category->getNameMg()}'");
                $io->text("Description: '{$category->getDescription()}'");
                $io->text("DescriptionMg: '{$category->getDescriptionMg()}'");
                $io->text("Is Valid: " . ($isValid ? 'Yes' : 'No'));
                
                if (!$isValid) {
                    $io->text("Validation errors:");
                    foreach ($violations as $violation) {
                        $io->text("  - " . $violation->getMessage());
                    }
                }
                
                // Check validation expectation
                if ($isValid === $testCase['expectedValid']) {
                    if ($isValid) {
                        // If valid, test slug generation
                        $this->entityManager->persist($category);
                        $this->entityManager->flush();
                        $this->entityManager->refresh($category);
                        
                        $createdCategories[] = $category;
                        
                        $slug = $category->getSlug();
                        $categoryId = (string) $category->getId();
                        
                        $io->text("Generated slug: '{$slug}'");
                        $io->text("Category ID: {$categoryId}");
                        
                        // Check which source was used for slug
                        $actualSource = 'unknown';
                        
                        if ($testCase['expectedSlugSource'] === 'name' && $testCase['name']) {
                            $expectedSlugBase = strtolower(str_replace(' ', '-', trim($testCase['name'])));
                            if (str_contains($slug, $expectedSlugBase)) {
                                $actualSource = 'name';
                            }
                        } elseif ($testCase['expectedSlugSource'] === 'nameMg' && $testCase['nameMg']) {
                            $expectedSlugBase = strtolower(str_replace(' ', '-', trim($testCase['nameMg'])));
                            if (str_contains($slug, $expectedSlugBase)) {
                                $actualSource = 'nameMg';
                            }
                        } elseif ($testCase['expectedSlugSource'] === 'id') {
                            if (str_contains($slug, $categoryId)) {
                                $actualSource = 'id';
                            }
                        }
                        
                        $io->text("Expected slug source: {$testCase['expectedSlugSource']}");
                        $io->text("Actual slug source: {$actualSource}");
                        
                        if ($actualSource === $testCase['expectedSlugSource']) {
                            $io->success("✅ Test PASSED");
                            $passedTests++;
                        } else {
                            $io->error("❌ Test FAILED - Slug source mismatch");
                        }
                    } else {
                        $io->success("✅ Test PASSED - Validation failed as expected");
                        $passedTests++;
                    }
                } else {
                    $io->error("❌ Test FAILED - Validation result mismatch");
                }
                
            } catch (\Exception $e) {
                $io->error("❌ Test FAILED - Exception: " . $e->getMessage());
            }
            
            $io->newLine();
        }
        
        // Cleanup created categories
        $io->section('Cleaning up test categories');
        foreach ($createdCategories as $category) {
            $this->entityManager->remove($category);
        }
        $this->entityManager->flush();
        $io->text('Test categories cleaned up.');
        
        $io->section('Test Results');
        $io->text("Passed: {$passedTests}/{$totalTests}");
        
        if ($passedTests === $totalTests) {
            $io->success('All tests passed! Category validation and slug generation are working correctly.');
            return Command::SUCCESS;
        } else {
            $io->error('Some tests failed. Please check the validation and slug generation logic.');
            return Command::FAILURE;
        }
    }
}