<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            // Programming Languages
            [
                'name' => 'PHP',
                'category' => 'programming_language',
                'description' => 'Server-side scripting language designed for web development',
                'popularity_score' => 85,
                'metadata' => ['related_skills' => ['Laravel', 'Symfony', 'WordPress']],
            ],
            [
                'name' => 'JavaScript',
                'category' => 'programming_language',
                'description' => 'High-level, interpreted programming language for web development',
                'popularity_score' => 95,
                'metadata' => ['related_skills' => ['React', 'Vue.js', 'Node.js']],
            ],
            [
                'name' => 'TypeScript',
                'category' => 'programming_language',
                'description' => 'Typed superset of JavaScript that compiles to plain JavaScript',
                'popularity_score' => 88,
                'metadata' => ['related_skills' => ['JavaScript', 'React', 'Angular']],
            ],
            [
                'name' => 'Python',
                'category' => 'programming_language',
                'description' => 'High-level programming language with emphasis on code readability',
                'popularity_score' => 92,
                'metadata' => ['related_skills' => ['Django', 'Flask', 'FastAPI']],
            ],
            [
                'name' => 'Java',
                'category' => 'programming_language',
                'description' => 'Class-based, object-oriented programming language',
                'popularity_score' => 80,
                'metadata' => ['related_skills' => ['Spring Boot', 'Hibernate']],
            ],
            [
                'name' => 'C#',
                'category' => 'programming_language',
                'description' => 'Modern, object-oriented programming language developed by Microsoft',
                'popularity_score' => 75,
                'metadata' => ['related_skills' => ['.NET', 'ASP.NET']],
            ],
            [
                'name' => 'Ruby',
                'category' => 'programming_language',
                'description' => 'Dynamic, open source programming language with focus on simplicity',
                'popularity_score' => 65,
                'metadata' => ['related_skills' => ['Ruby on Rails']],
            ],
            [
                'name' => 'Go',
                'category' => 'programming_language',
                'description' => 'Statically typed, compiled programming language designed at Google',
                'popularity_score' => 78,
                'metadata' => ['related_skills' => ['Kubernetes', 'Docker']],
            ],

            // Backend Frameworks
            [
                'name' => 'Laravel',
                'category' => 'framework',
                'description' => 'PHP web application framework with expressive, elegant syntax',
                'popularity_score' => 90,
                'metadata' => ['related_skills' => ['PHP', 'MySQL', 'PostgreSQL']],
            ],
            [
                'name' => 'Symfony',
                'category' => 'framework',
                'description' => 'PHP framework for web applications and reusable components',
                'popularity_score' => 70,
                'metadata' => ['related_skills' => ['PHP', 'Doctrine']],
            ],
            [
                'name' => 'Django',
                'category' => 'framework',
                'description' => 'High-level Python web framework that encourages rapid development',
                'popularity_score' => 82,
                'metadata' => ['related_skills' => ['Python', 'PostgreSQL']],
            ],
            [
                'name' => 'Flask',
                'category' => 'framework',
                'description' => 'Lightweight Python web framework',
                'popularity_score' => 75,
                'metadata' => ['related_skills' => ['Python', 'SQLAlchemy']],
            ],
            [
                'name' => 'FastAPI',
                'category' => 'framework',
                'description' => 'Modern, fast web framework for building APIs with Python',
                'popularity_score' => 85,
                'metadata' => ['related_skills' => ['Python', 'Pydantic']],
            ],
            [
                'name' => 'Express.js',
                'category' => 'framework',
                'description' => 'Fast, unopinionated web framework for Node.js',
                'popularity_score' => 88,
                'metadata' => ['related_skills' => ['Node.js', 'JavaScript']],
            ],
            [
                'name' => 'NestJS',
                'category' => 'framework',
                'description' => 'Progressive Node.js framework for building efficient server-side applications',
                'popularity_score' => 80,
                'metadata' => ['related_skills' => ['TypeScript', 'Node.js']],
            ],
            [
                'name' => 'Spring Boot',
                'category' => 'framework',
                'description' => 'Java-based framework for creating stand-alone Spring applications',
                'popularity_score' => 78,
                'metadata' => ['related_skills' => ['Java', 'Spring']],
            ],
            [
                'name' => 'Ruby on Rails',
                'category' => 'framework',
                'description' => 'Server-side web application framework written in Ruby',
                'popularity_score' => 68,
                'metadata' => ['related_skills' => ['Ruby', 'PostgreSQL']],
            ],
            [
                'name' => 'ASP.NET Core',
                'category' => 'framework',
                'description' => 'Cross-platform, high-performance framework for building modern applications',
                'popularity_score' => 72,
                'metadata' => ['related_skills' => ['C#', '.NET']],
            ],

            // Frontend Frameworks
            [
                'name' => 'React',
                'category' => 'frontend',
                'description' => 'JavaScript library for building user interfaces',
                'popularity_score' => 95,
                'metadata' => ['related_skills' => ['JavaScript', 'TypeScript', 'Next.js']],
            ],
            [
                'name' => 'Vue.js',
                'category' => 'frontend',
                'description' => 'Progressive JavaScript framework for building user interfaces',
                'popularity_score' => 85,
                'metadata' => ['related_skills' => ['JavaScript', 'Nuxt.js']],
            ],
            [
                'name' => 'Angular',
                'category' => 'frontend',
                'description' => 'Platform for building mobile and desktop web applications',
                'popularity_score' => 75,
                'metadata' => ['related_skills' => ['TypeScript', 'RxJS']],
            ],
            [
                'name' => 'Next.js',
                'category' => 'frontend',
                'description' => 'React framework for production with hybrid static & server rendering',
                'popularity_score' => 90,
                'metadata' => ['related_skills' => ['React', 'TypeScript']],
            ],
            [
                'name' => 'Nuxt.js',
                'category' => 'frontend',
                'description' => 'Vue.js framework for creating server-side rendered applications',
                'popularity_score' => 78,
                'metadata' => ['related_skills' => ['Vue.js', 'JavaScript']],
            ],
            [
                'name' => 'Svelte',
                'category' => 'frontend',
                'description' => 'Cybernetically enhanced web apps compiler',
                'popularity_score' => 72,
                'metadata' => ['related_skills' => ['JavaScript']],
            ],

            // Databases
            [
                'name' => 'MySQL',
                'category' => 'database',
                'description' => 'Open-source relational database management system',
                'popularity_score' => 85,
                'metadata' => ['related_skills' => ['SQL', 'PHP']],
            ],
            [
                'name' => 'PostgreSQL',
                'category' => 'database',
                'description' => 'Advanced open source relational database',
                'popularity_score' => 88,
                'metadata' => ['related_skills' => ['SQL', 'Laravel']],
            ],
            [
                'name' => 'MongoDB',
                'category' => 'database',
                'description' => 'NoSQL document-oriented database',
                'popularity_score' => 82,
                'metadata' => ['related_skills' => ['Node.js', 'Express.js']],
            ],
            [
                'name' => 'Redis',
                'category' => 'database',
                'description' => 'In-memory data structure store, used as database, cache, and message broker',
                'popularity_score' => 80,
                'metadata' => ['related_skills' => ['Caching', 'Laravel']],
            ],
            [
                'name' => 'Elasticsearch',
                'category' => 'database',
                'description' => 'Distributed, RESTful search and analytics engine',
                'popularity_score' => 75,
                'metadata' => ['related_skills' => ['Kibana', 'Logstash']],
            ],

            // CMS & E-commerce
            [
                'name' => 'WordPress',
                'category' => 'cms',
                'description' => 'Open source content management system',
                'popularity_score' => 80,
                'metadata' => ['related_skills' => ['PHP', 'MySQL']],
            ],
            [
                'name' => 'Magento 2',
                'category' => 'cms',
                'description' => 'Open-source e-commerce platform',
                'popularity_score' => 70,
                'metadata' => ['related_skills' => ['PHP', 'MySQL', 'Elasticsearch']],
            ],
            [
                'name' => 'Shopify',
                'category' => 'cms',
                'description' => 'E-commerce platform for online stores',
                'popularity_score' => 75,
                'metadata' => ['related_skills' => ['Liquid', 'JavaScript']],
            ],
            [
                'name' => 'WooCommerce',
                'category' => 'cms',
                'description' => 'E-commerce plugin for WordPress',
                'popularity_score' => 72,
                'metadata' => ['related_skills' => ['WordPress', 'PHP']],
            ],
            [
                'name' => 'Drupal',
                'category' => 'cms',
                'description' => 'Open-source content management framework',
                'popularity_score' => 60,
                'metadata' => ['related_skills' => ['PHP', 'Symfony']],
            ],

            // Mobile Development
            [
                'name' => 'React Native',
                'category' => 'mobile',
                'description' => 'Framework for building native mobile apps using React',
                'popularity_score' => 85,
                'metadata' => ['related_skills' => ['React', 'JavaScript']],
            ],
            [
                'name' => 'Flutter',
                'category' => 'mobile',
                'description' => 'UI toolkit for building natively compiled applications',
                'popularity_score' => 82,
                'metadata' => ['related_skills' => ['Dart']],
            ],
            [
                'name' => 'Swift',
                'category' => 'mobile',
                'description' => 'Programming language for iOS, macOS, watchOS, and tvOS',
                'popularity_score' => 75,
                'metadata' => ['related_skills' => ['iOS', 'Xcode']],
            ],
            [
                'name' => 'Kotlin',
                'category' => 'mobile',
                'description' => 'Modern programming language for Android development',
                'popularity_score' => 78,
                'metadata' => ['related_skills' => ['Android', 'Java']],
            ],

            // DevOps & Cloud
            [
                'name' => 'Docker',
                'category' => 'devops',
                'description' => 'Platform for developing, shipping, and running applications in containers',
                'popularity_score' => 90,
                'metadata' => ['related_skills' => ['Kubernetes', 'Linux']],
            ],
            [
                'name' => 'Kubernetes',
                'category' => 'devops',
                'description' => 'Container orchestration platform',
                'popularity_score' => 85,
                'metadata' => ['related_skills' => ['Docker', 'Cloud']],
            ],
            [
                'name' => 'AWS',
                'category' => 'cloud',
                'description' => 'Amazon Web Services cloud computing platform',
                'popularity_score' => 92,
                'metadata' => ['related_skills' => ['EC2', 'S3', 'Lambda']],
            ],
            [
                'name' => 'Azure',
                'category' => 'cloud',
                'description' => 'Microsoft cloud computing service',
                'popularity_score' => 80,
                'metadata' => ['related_skills' => ['C#', '.NET']],
            ],
            [
                'name' => 'Google Cloud Platform',
                'category' => 'cloud',
                'description' => 'Suite of cloud computing services by Google',
                'popularity_score' => 78,
                'metadata' => ['related_skills' => ['Kubernetes', 'BigQuery']],
            ],
            [
                'name' => 'CI/CD',
                'category' => 'devops',
                'description' => 'Continuous Integration and Continuous Deployment practices',
                'popularity_score' => 88,
                'metadata' => ['related_skills' => ['Jenkins', 'GitLab CI', 'GitHub Actions']],
            ],
            [
                'name' => 'Terraform',
                'category' => 'devops',
                'description' => 'Infrastructure as Code software tool',
                'popularity_score' => 82,
                'metadata' => ['related_skills' => ['AWS', 'Azure']],
            ],

            // Testing
            [
                'name' => 'Jest',
                'category' => 'testing',
                'description' => 'JavaScript testing framework',
                'popularity_score' => 85,
                'metadata' => ['related_skills' => ['JavaScript', 'React']],
            ],
            [
                'name' => 'PHPUnit',
                'category' => 'testing',
                'description' => 'Unit testing framework for PHP',
                'popularity_score' => 80,
                'metadata' => ['related_skills' => ['PHP', 'Laravel']],
            ],
            [
                'name' => 'Pytest',
                'category' => 'testing',
                'description' => 'Testing framework for Python',
                'popularity_score' => 82,
                'metadata' => ['related_skills' => ['Python', 'Django']],
            ],
            [
                'name' => 'Cypress',
                'category' => 'testing',
                'description' => 'End-to-end testing framework for web applications',
                'popularity_score' => 78,
                'metadata' => ['related_skills' => ['JavaScript', 'React']],
            ],

            // Additional Popular Technologies
            [
                'name' => 'GraphQL',
                'category' => 'backend',
                'description' => 'Query language for APIs',
                'popularity_score' => 80,
                'metadata' => ['related_skills' => ['Apollo', 'Node.js']],
            ],
            [
                'name' => 'REST API',
                'category' => 'backend',
                'description' => 'Architectural style for distributed hypermedia systems',
                'popularity_score' => 90,
                'metadata' => ['related_skills' => ['HTTP', 'JSON']],
            ],
            [
                'name' => 'Tailwind CSS',
                'category' => 'frontend',
                'description' => 'Utility-first CSS framework',
                'popularity_score' => 88,
                'metadata' => ['related_skills' => ['CSS', 'React']],
            ],
            [
                'name' => 'Bootstrap',
                'category' => 'frontend',
                'description' => 'Popular CSS framework for responsive web design',
                'popularity_score' => 75,
                'metadata' => ['related_skills' => ['CSS', 'JavaScript']],
            ],
        ];

        foreach ($skills as $skill) {
            Skill::create($skill);
        }

        $this->command->info('Skills seeded successfully!');
        $this->command->info('Total skills created: ' . count($skills));
    }
}
