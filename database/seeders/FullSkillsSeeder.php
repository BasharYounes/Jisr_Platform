<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FullSkillsSeeder
 *
 * يملأ الجداول الأربع لكل مسار مهني:
 *   1. skills              — الاسم + الفئة + normalized_name
 *   2. skill_aliases       — الأسماء البديلة (ما يكتبه الطالب في الـ CV)
 *   3. skill_level_definitions — تعريف مستويات 1-5 لكل مهارة
 *   4. career_path_skills  — ربط المهارة بالمسار + الوزن + IsCore
 *
 * المسارات المشمولة:
 *   - Backend Developer
 *   - Frontend Developer
 *   - Mobile Developer (Flutter)
 *   - DevOps / Cloud Engineer
 */
class FullSkillsSeeder extends Seeder
{
    public function run(): void
    {
        // ═══════════════════════════════════════════════════════════
        // الخطوة 1: إنشاء المسارات المهنية
        // ═══════════════════════════════════════════════════════════
        $careerPaths = [
            'Backend Developer' => 'Backend development track: server-side programming, REST APIs, databases, and version control.',
            'Frontend Developer' => 'Frontend development track: UI building, JavaScript frameworks, styling, and browser interactions.',
            'Mobile Developer' => 'Mobile development track using Flutter/Dart for cross-platform iOS and Android applications.',
            'DevOps Engineer' => 'DevOps track: CI/CD pipelines, containerization, cloud infrastructure, and system reliability.',
        ];

        foreach ($careerPaths as $name => $description) {
            DB::table('career_paths')->updateOrInsert(
                ['Name' => $name],
                ['Description' => $description, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // ═══════════════════════════════════════════════════════════
        // الخطوة 2: تعريف كل المهارات مع بياناتها الكاملة
        // ═══════════════════════════════════════════════════════════
        // الهيكل:
        // 'SkillName' => [
        //     'category'       => فئة المهارة
        //     'normalized'     => الاسم المعياري
        //     'aliases'        => ما قد يكتبه الطالب في الـ CV
        //     'levels'         => تعريف مستويات 1-5
        //     'career_paths'   => المسارات التي تنتمي إليها مع الوزن والأولوية
        // ]
        $skills = [

            // ──────────────────────────────────────────────────────
            // Backend Developer Skills
            // ──────────────────────────────────────────────────────

            'Python' => [
                'category' => 'Programming Language',
                'normalized' => 'python',
                'aliases' => ['python3', 'py', 'python programming'],
                'levels' => [
                    1 => ['title' => 'Beginner Basics',          'description' => 'Understands variables, conditions, loops, and basic functions.',                                                    'indicators' => ['if statements', 'loops', 'basic functions', 'simple scripts']],
                    2 => ['title' => 'Practical Foundations',    'description' => 'Uses lists, dictionaries, files, modules, and writes small practical scripts.',                                    'indicators' => ['list/dict usage', 'file handling', 'imports', 'small scripts']],
                    3 => ['title' => 'Intermediate Development', 'description' => 'Understands exceptions, OOP basics, testing basics, and writes structured code.',                                 'indicators' => ['exception handling', 'classes', 'basic testing', 'modular code']],
                    4 => ['title' => 'Advanced Python',          'description' => 'Uses decorators, generators, profiling, and writes optimized code.',                                              'indicators' => ['decorators', 'generators', 'performance awareness', 'clean abstractions']],
                    5 => ['title' => 'Expert Level',             'description' => 'Designs robust systems, contributes to advanced libraries, and handles advanced async patterns.',                 'indicators' => ['system design', 'asyncio', 'advanced architecture', 'library design']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 3.0, 'weight' => 1.00, 'is_core' => true],
                ],
            ],

            'Flask' => [
                'category' => 'Framework',
                'normalized' => 'flask',
                'aliases' => ['flask framework', 'flask api', 'flask web'],
                'levels' => [
                    1 => ['title' => 'Basic Routing',             'description' => 'Can build a minimal Flask app with simple routes.',                                                              'indicators' => ['app creation', 'route decorator', 'basic response']],
                    2 => ['title' => 'Simple API Development',    'description' => 'Builds simple GET/POST endpoints and handles request data.',                                                     'indicators' => ['GET/POST', 'request parsing', 'JSON response']],
                    3 => ['title' => 'Structured Flask',          'description' => 'Uses blueprints, validation basics, and simple error handling.',                                                 'indicators' => ['blueprints', 'validation', 'error handling']],
                    4 => ['title' => 'Production-minded Flask',   'description' => 'Handles authentication, configuration separation, and app structure.',                                           'indicators' => ['auth basics', 'config separation', 'project structure']],
                    5 => ['title' => 'Advanced Flask Engineering', 'description' => 'Designs scalable Flask services with testing, deployment awareness, and architecture decisions.',               'indicators' => ['testing', 'scalability', 'service design', 'deployment readiness']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 2.5, 'weight' => 0.90, 'is_core' => true],
                ],
            ],

            'SQL' => [
                'category' => 'Database',
                'normalized' => 'sql',
                'aliases' => ['mysql', 'postgresql', 'postgres', 'sqlite', 'sql database', 'pl/sql', 'mariadb'],
                'levels' => [
                    1 => ['title' => 'Basic Queries',             'description' => 'Can write simple SELECT statements and basic filtering.',                                                        'indicators' => ['SELECT', 'WHERE', 'basic sorting']],
                    2 => ['title' => 'Intermediate Querying',     'description' => 'Uses JOIN, GROUP BY, and aggregate functions.',                                                                  'indicators' => ['JOIN', 'GROUP BY', 'COUNT/SUM', 'HAVING']],
                    3 => ['title' => 'Practical Data Handling',   'description' => 'Writes subqueries, handles complex filters, and understands normalization basics.',                              'indicators' => ['subqueries', 'normalization', 'multi-condition querying']],
                    4 => ['title' => 'Optimization Awareness',    'description' => 'Understands indexes, query optimization basics, and performance tradeoffs.',                                     'indicators' => ['indexes', 'query plans', 'performance awareness']],
                    5 => ['title' => 'Advanced Database Thinking', 'description' => 'Designs efficient schemas and optimizes complex data access patterns.',                                        'indicators' => ['schema design', 'optimization', 'advanced relational modeling']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 2.5, 'weight' => 0.95, 'is_core' => true],
                    'DevOps Engineer' => ['required_level' => 1.5, 'weight' => 0.50, 'is_core' => false],
                ],
            ],

            'Git' => [
                'category' => 'Version Control',
                'normalized' => 'git',
                'aliases' => ['github', 'gitlab', 'git version control', 'bitbucket', 'git scm'],
                'levels' => [
                    1 => ['title' => 'Basic Version Control',    'description' => 'Can init repositories, commit changes, and push code.',                                                          'indicators' => ['git init', 'commit', 'push']],
                    2 => ['title' => 'Daily Collaboration',      'description' => 'Uses branches, pull, merge, and resolves simple conflicts.',                                                     'indicators' => ['branching', 'pull', 'merge', 'conflict resolution']],
                    3 => ['title' => 'Team Workflow Usage',      'description' => 'Understands pull requests, rebasing basics, and cleaner commit history.',                                        'indicators' => ['pull requests', 'rebase', 'history awareness']],
                    4 => ['title' => 'Advanced Collaboration',   'description' => 'Handles complex conflict resolution and structured team workflows.',                                             'indicators' => ['advanced conflicts', 'workflow strategy', 'release branching']],
                    5 => ['title' => 'Expert Git Usage',         'description' => 'Can guide repository strategy and recover from complex history issues safely.',                                  'indicators' => ['repository strategy', 'history recovery', 'advanced git internals']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 2.0, 'weight' => 0.80, 'is_core' => true],
                    'Frontend Developer' => ['required_level' => 2.0, 'weight' => 0.80, 'is_core' => true],
                    'Mobile Developer' => ['required_level' => 2.0, 'weight' => 0.75, 'is_core' => true],
                    'DevOps Engineer' => ['required_level' => 3.0, 'weight' => 0.90, 'is_core' => true],
                ],
            ],

            'Laravel' => [
                'category' => 'Framework',
                'normalized' => 'laravel',
                'aliases' => ['laravel framework', 'laravel php', 'lumen'],
                'levels' => [
                    1 => ['title' => 'Laravel Basics',            'description' => 'Understands routing, controllers, and blade templates.',                                                        'indicators' => ['routes', 'controllers', 'blade']],
                    2 => ['title' => 'Eloquent & MVC',            'description' => 'Uses Eloquent ORM, migrations, and builds simple CRUD apps.',                                                   'indicators' => ['Eloquent', 'migrations', 'CRUD', 'relationships']],
                    3 => ['title' => 'Intermediate Laravel',      'description' => 'Implements middleware, authentication, form validation, and APIs with Laravel.',                                'indicators' => ['middleware', 'auth', 'validation', 'API resources']],
                    4 => ['title' => 'Advanced Laravel',          'description' => 'Uses queues, events, service containers, and designs clean service layers.',                                    'indicators' => ['queues', 'events', 'service container', 'repository pattern']],
                    5 => ['title' => 'Laravel Architecture',      'description' => 'Designs scalable Laravel applications with domain-driven patterns and performance optimization.',               'indicators' => ['DDD', 'caching', 'scaling', 'package development']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 2.5, 'weight' => 0.90, 'is_core' => false],
                ],
            ],

            'Node.js' => [
                'category' => 'Runtime',
                'normalized' => 'nodejs',
                'aliases' => ['node', 'nodejs', 'node js', 'express', 'expressjs', 'express.js'],
                'levels' => [
                    1 => ['title' => 'Node Basics',               'description' => 'Understands event loop, modules, and builds simple HTTP servers.',                                              'indicators' => ['http module', 'require/import', 'basic server']],
                    2 => ['title' => 'Express Fundamentals',      'description' => 'Builds REST APIs with Express, handles middleware and basic routing.',                                          'indicators' => ['Express routes', 'middleware', 'JSON APIs']],
                    3 => ['title' => 'Intermediate Node',         'description' => 'Handles async/await, file system, environment config, and error handling.',                                     'indicators' => ['async/await', 'fs module', 'dotenv', 'error middleware']],
                    4 => ['title' => 'Advanced Node.js',          'description' => 'Implements authentication, works with databases via ORM, and writes testable services.',                       'indicators' => ['JWT auth', 'Sequelize/Mongoose', 'unit tests', 'service layer']],
                    5 => ['title' => 'Node.js Architecture',      'description' => 'Designs production-grade Node services with clustering, caching, and CI awareness.',                           'indicators' => ['clustering', 'Redis caching', 'CI/CD', 'microservices']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 2.0, 'weight' => 0.85, 'is_core' => false],
                ],
            ],

            'REST API' => [
                'category' => 'Architecture',
                'normalized' => 'rest_api',
                'aliases' => ['rest', 'restful', 'restful api', 'rest apis', 'http api', 'web api'],
                'levels' => [
                    1 => ['title' => 'API Consumer',              'description' => 'Understands HTTP methods and can consume existing APIs.',                                                       'indicators' => ['GET/POST/PUT/DELETE', 'HTTP status codes', 'Postman usage']],
                    2 => ['title' => 'Basic API Builder',         'description' => 'Builds simple REST endpoints with proper status codes and JSON responses.',                                     'indicators' => ['endpoint design', 'JSON response', 'status codes']],
                    3 => ['title' => 'Structured API Design',     'description' => 'Designs resource-based routes, handles versioning and authentication.',                                         'indicators' => ['resource naming', 'versioning', 'auth headers']],
                    4 => ['title' => 'API Best Practices',        'description' => 'Applies pagination, filtering, rate limiting, and documents APIs.',                                             'indicators' => ['pagination', 'filtering', 'rate limiting', 'OpenAPI/Swagger']],
                    5 => ['title' => 'API Architecture',          'description' => 'Designs scalable API contracts with versioning strategy and backward compatibility.',                           'indicators' => ['API contracts', 'backward compatibility', 'gateway patterns']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 2.5, 'weight' => 0.95, 'is_core' => true],
                    'Mobile Developer' => ['required_level' => 2.0, 'weight' => 0.80, 'is_core' => true],
                    'Frontend Developer' => ['required_level' => 1.5, 'weight' => 0.60, 'is_core' => false],
                ],
            ],

            'Docker' => [
                'category' => 'Containerization',
                'normalized' => 'docker',
                'aliases' => ['docker container', 'dockerfile', 'docker compose', 'docker-compose', 'containerization'],
                'levels' => [
                    1 => ['title' => 'Docker Basics',             'description' => 'Can pull images, run containers, and understand basic Docker concepts.',                                        'indicators' => ['docker pull', 'docker run', 'docker ps', 'images']],
                    2 => ['title' => 'Dockerfile Author',         'description' => 'Writes Dockerfiles to containerize simple applications.',                                                       'indicators' => ['Dockerfile', 'build', 'CMD/ENTRYPOINT', 'layers']],
                    3 => ['title' => 'Docker Compose User',       'description' => 'Defines multi-container apps with Docker Compose.',                                                             'indicators' => ['docker-compose.yml', 'services', 'volumes', 'networks']],
                    4 => ['title' => 'Optimization Awareness',    'description' => 'Optimizes image sizes, manages volumes, and understands networking in Docker.',                                 'indicators' => ['multi-stage builds', 'volume mounts', 'bridge networks']],
                    5 => ['title' => 'Container Orchestration',   'description' => 'Integrates Docker with CI/CD and understands orchestration concepts.',                                          'indicators' => ['CI/CD integration', 'registry', 'Kubernetes basics']],
                ],
                'career_paths' => [
                    'Backend Developer' => ['required_level' => 2.0, 'weight' => 0.75, 'is_core' => false],
                    'DevOps Engineer' => ['required_level' => 3.5, 'weight' => 1.00, 'is_core' => true],
                ],
            ],

            // ──────────────────────────────────────────────────────
            // Frontend Developer Skills
            // ──────────────────────────────────────────────────────

            'HTML' => [
                'category' => 'Markup Language',
                'normalized' => 'html',
                'aliases' => ['html5', 'hypertext markup', 'html/css'],
                'levels' => [
                    1 => ['title' => 'Basic Markup',              'description' => 'Knows common HTML tags and builds basic static pages.',                                                         'indicators' => ['headings', 'paragraphs', 'links', 'images', 'lists']],
                    2 => ['title' => 'Structured HTML',           'description' => 'Uses semantic elements, forms, and tables correctly.',                                                          'indicators' => ['semantic tags', 'forms', 'input types', 'tables']],
                    3 => ['title' => 'Accessible HTML',           'description' => 'Applies accessibility attributes and understands document structure.',                                          'indicators' => ['aria attributes', 'landmark roles', 'meta tags', 'SEO basics']],
                    4 => ['title' => 'Advanced HTML Patterns',    'description' => 'Uses advanced form features, custom data attributes, and optimizes for performance.',                           'indicators' => ['data attributes', 'template tag', 'lazy loading', 'responsive images']],
                    5 => ['title' => 'HTML Mastery',              'description' => 'Deep knowledge of browser rendering and HTML spec nuances.',                                                    'indicators' => ['browser rendering', 'spec knowledge', 'shadow DOM basics']],
                ],
                'career_paths' => [
                    'Frontend Developer' => ['required_level' => 3.0, 'weight' => 0.90, 'is_core' => true],
                ],
            ],

            'CSS' => [
                'category' => 'Styling',
                'normalized' => 'css',
                'aliases' => ['css3', 'stylesheets', 'cascading style sheets', 'sass', 'scss', 'less'],
                'levels' => [
                    1 => ['title' => 'Basic Styling',             'description' => 'Applies colors, fonts, margins, and basic layout with CSS.',                                                    'indicators' => ['selectors', 'box model', 'colors', 'fonts']],
                    2 => ['title' => 'Layout Fundamentals',       'description' => 'Uses Flexbox, basic grid, and responsive units.',                                                               'indicators' => ['Flexbox', 'media queries', 'relative units', 'basic grid']],
                    3 => ['title' => 'Intermediate CSS',          'description' => 'Uses CSS Grid, transitions, animations, and variables.',                                                        'indicators' => ['CSS Grid', 'transitions', 'keyframes', 'custom properties']],
                    4 => ['title' => 'Advanced CSS',              'description' => 'Writes modular CSS with methodologies (BEM/SMACSS) and preprocessors (SASS).',                                 'indicators' => ['BEM', 'SASS/SCSS', 'architecture', 'theming']],
                    5 => ['title' => 'CSS Mastery',               'description' => 'Builds complex design systems and optimizes CSS performance.',                                                  'indicators' => ['design systems', 'critical CSS', 'paint optimization', 'CSS-in-JS']],
                ],
                'career_paths' => [
                    'Frontend Developer' => ['required_level' => 3.0, 'weight' => 0.90, 'is_core' => true],
                ],
            ],

            'JavaScript' => [
                'category' => 'Programming Language',
                'normalized' => 'javascript',
                'aliases' => ['js', 'vanilla js', 'es6', 'es2015', 'ecmascript', 'javascript es6'],
                'levels' => [
                    1 => ['title' => 'JS Basics',                 'description' => 'Understands variables, functions, DOM manipulation, and event listeners.',                                     'indicators' => ['variables', 'functions', 'DOM', 'event listeners']],
                    2 => ['title' => 'Practical JS',              'description' => 'Uses arrays, objects, ES6 features, and handles async basics.',                                                 'indicators' => ['arrow functions', 'destructuring', 'promises basics', 'fetch API']],
                    3 => ['title' => 'Intermediate JS',           'description' => 'Understands closures, async/await, modules, and error handling.',                                              'indicators' => ['closures', 'async/await', 'ES modules', 'try/catch']],
                    4 => ['title' => 'Advanced JS',               'description' => 'Applies design patterns, prototypal inheritance, and performance techniques.',                                  'indicators' => ['design patterns', 'prototype chain', 'event loop', 'Web APIs']],
                    5 => ['title' => 'JS Expert',                 'description' => 'Deep understanding of engine behavior, optimization, and advanced patterns.',                                   'indicators' => ['V8 internals', 'memory management', 'metaprogramming', 'compiler hints']],
                ],
                'career_paths' => [
                    'Frontend Developer' => ['required_level' => 3.5, 'weight' => 1.00, 'is_core' => true],
                    'Mobile Developer' => ['required_level' => 1.0, 'weight' => 0.40, 'is_core' => false],
                ],
            ],

            'React' => [
                'category' => 'Framework',
                'normalized' => 'react',
                'aliases' => ['reactjs', 'react.js', 'react js', 'react hooks', 'next.js', 'nextjs'],
                'levels' => [
                    1 => ['title' => 'React Basics',              'description' => 'Understands components, JSX, and props.',                                                                       'indicators' => ['components', 'JSX', 'props', 'rendering']],
                    2 => ['title' => 'State & Events',            'description' => 'Uses useState, useEffect, and handles user events.',                                                            'indicators' => ['useState', 'useEffect', 'event handling', 'conditional rendering']],
                    3 => ['title' => 'Component Architecture',    'description' => 'Manages component composition, custom hooks, and basic routing.',                                              'indicators' => ['custom hooks', 'React Router', 'component patterns', 'lifting state']],
                    4 => ['title' => 'Advanced React',            'description' => 'Applies context, performance optimization, and state management libraries.',                                    'indicators' => ['Context API', 'Redux/Zustand', 'React.memo', 'code splitting']],
                    5 => ['title' => 'React Architecture',        'description' => 'Builds large-scale React apps with testing, SSR, and performance budgets.',                                    'indicators' => ['SSR/SSG', 'testing library', 'design patterns', 'performance profiling']],
                ],
                'career_paths' => [
                    'Frontend Developer' => ['required_level' => 3.0, 'weight' => 0.95, 'is_core' => true],
                ],
            ],

            'Vue.js' => [
                'category' => 'Framework',
                'normalized' => 'vuejs',
                'aliases' => ['vue', 'vuejs', 'vue 3', 'vue.js', 'nuxt', 'nuxtjs', 'nuxt.js'],
                'levels' => [
                    1 => ['title' => 'Vue Basics',                'description' => 'Understands Vue directives, data binding, and basic component structure.',                                     'indicators' => ['v-bind', 'v-model', 'v-if', 'v-for', 'basic components']],
                    2 => ['title' => 'Vue Components',            'description' => 'Builds components with props, emits, and lifecycle hooks.',                                                     'indicators' => ['props', 'emits', 'lifecycle hooks', 'computed']],
                    3 => ['title' => 'Intermediate Vue',          'description' => 'Uses Composition API, Vue Router, and basic Pinia/Vuex.',                                                       'indicators' => ['Composition API', 'Vue Router', 'Pinia basics', 'watchers']],
                    4 => ['title' => 'Advanced Vue',              'description' => 'Manages complex state, performance optimization, and reusable composables.',                                    'indicators' => ['composables', 'Pinia advanced', 'lazy loading', 'Suspense']],
                    5 => ['title' => 'Vue Architecture',          'description' => 'Builds large-scale Vue apps with SSR (Nuxt), testing, and design systems.',                                    'indicators' => ['Nuxt.js', 'testing', 'design system integration', 'SSR']],
                ],
                'career_paths' => [
                    'Frontend Developer' => ['required_level' => 2.5, 'weight' => 0.90, 'is_core' => false],
                ],
            ],

            'TypeScript' => [
                'category' => 'Programming Language',
                'normalized' => 'typescript',
                'aliases' => ['ts', 'typescript js', 'typed javascript'],
                'levels' => [
                    1 => ['title' => 'TS Basics',                 'description' => 'Understands basic type annotations and compiles TS to JS.',                                                     'indicators' => ['type annotations', 'interfaces basics', 'tsc compiler', 'tsconfig']],
                    2 => ['title' => 'Practical TypeScript',      'description' => 'Uses interfaces, enums, generics basics, and types in functions.',                                              'indicators' => ['interfaces', 'enums', 'type aliases', 'function types']],
                    3 => ['title' => 'Intermediate TS',           'description' => 'Applies generics, utility types, and strict mode configurations.',                                              'indicators' => ['generics', 'utility types', 'strict mode', 'type narrowing']],
                    4 => ['title' => 'Advanced TypeScript',       'description' => 'Uses conditional types, mapped types, and decorators.',                                                         'indicators' => ['conditional types', 'mapped types', 'decorators', 'declaration merging']],
                    5 => ['title' => 'TS Architecture',           'description' => 'Designs type-safe systems with advanced patterns and custom type utilities.',                                   'indicators' => ['type utilities', 'DI patterns', 'module augmentation', 'complex inference']],
                ],
                'career_paths' => [
                    'Frontend Developer' => ['required_level' => 2.5, 'weight' => 0.85, 'is_core' => false],
                    'Backend Developer' => ['required_level' => 2.0, 'weight' => 0.70, 'is_core' => false],
                ],
            ],

            'Tailwind CSS' => [
                'category' => 'Styling',
                'normalized' => 'tailwind_css',
                'aliases' => ['tailwind', 'tailwindcss', 'tailwind css', 'utility-first css'],
                'levels' => [
                    1 => ['title' => 'Tailwind Basics',           'description' => 'Uses utility classes for basic layout and styling.',                                                            'indicators' => ['flex/grid utilities', 'spacing', 'colors', 'typography']],
                    2 => ['title' => 'Responsive Design',         'description' => 'Applies responsive breakpoints and hover/focus states.',                                                        'indicators' => ['sm/md/lg breakpoints', 'hover:', 'focus:', 'dark: mode']],
                    3 => ['title' => 'Component Building',        'description' => 'Builds reusable UI components and extracts custom classes.',                                                    'indicators' => ['@apply directive', 'component extraction', 'plugin usage']],
                    4 => ['title' => 'Advanced Tailwind',         'description' => 'Configures custom design tokens and extends the theme.',                                                        'indicators' => ['tailwind.config.js', 'theme extension', 'custom plugins']],
                    5 => ['title' => 'Design System Mastery',     'description' => 'Builds full design systems and integrates Tailwind in large-scale projects.',                                   'indicators' => ['design tokens', 'component library', 'purge optimization']],
                ],
                'career_paths' => [
                    'Frontend Developer' => ['required_level' => 2.0, 'weight' => 0.75, 'is_core' => false],
                ],
            ],

            // ──────────────────────────────────────────────────────
            // Mobile Developer (Flutter) Skills
            // ──────────────────────────────────────────────────────

            'Flutter' => [
                'category' => 'Mobile Framework',
                'normalized' => 'flutter',
                'aliases' => ['flutter sdk', 'flutter framework', 'flutter app', 'flutter development'],
                'levels' => [
                    1 => ['title' => 'Flutter Basics',            'description' => 'Understands widgets, hot reload, and builds simple layouts.',                                                   'indicators' => ['StatelessWidget', 'StatefulWidget', 'MaterialApp', 'Scaffold']],
                    2 => ['title' => 'Practical Flutter',         'description' => 'Builds multi-screen apps with navigation and basic state management.',                                          'indicators' => ['Navigator', 'setState', 'Column/Row/ListView', 'Form widgets']],
                    3 => ['title' => 'Intermediate Flutter',      'description' => 'Uses state management solutions, handles API calls, and manages app lifecycle.',                                'indicators' => ['Provider/Riverpod', 'http package', 'FutureBuilder', 'lifecycle']],
                    4 => ['title' => 'Advanced Flutter',          'description' => 'Applies clean architecture, advanced animations, and platform channels.',                                      'indicators' => ['BLoC pattern', 'custom animations', 'platform channels', 'testing']],
                    5 => ['title' => 'Flutter Architecture',      'description' => 'Designs production-ready Flutter apps with CI/CD and performance optimization.',                               'indicators' => ['CI/CD', 'flavors', 'performance profiling', 'code generation']],
                ],
                'career_paths' => [
                    'Mobile Developer' => ['required_level' => 3.0, 'weight' => 1.00, 'is_core' => true],
                ],
            ],

            'Dart' => [
                'category' => 'Programming Language',
                'normalized' => 'dart',
                'aliases' => ['dart language', 'dart programming', 'dart lang'],
                'levels' => [
                    1 => ['title' => 'Dart Basics',               'description' => 'Understands Dart syntax, variables, and basic functions.',                                                      'indicators' => ['variables', 'functions', 'basic OOP', 'null safety basics']],
                    2 => ['title' => 'Practical Dart',            'description' => 'Uses lists, maps, classes, and null safety confidently.',                                                       'indicators' => ['null safety', 'collections', 'classes', 'mixins basics']],
                    3 => ['title' => 'Intermediate Dart',         'description' => 'Applies async/await, streams, and generics in Dart.',                                                           'indicators' => ['async/await', 'Stream', 'Future', 'generics']],
                    4 => ['title' => 'Advanced Dart',             'description' => 'Designs with interfaces, abstract classes, and advanced type system.',                                          'indicators' => ['abstract classes', 'interfaces', 'extension methods', 'typedefs']],
                    5 => ['title' => 'Dart Expert',               'description' => 'Masters Dart internals, isolates, and writes performance-optimized Dart code.',                                'indicators' => ['isolates', 'FFI', 'compiler flags', 'advanced patterns']],
                ],
                'career_paths' => [
                    'Mobile Developer' => ['required_level' => 3.0, 'weight' => 0.95, 'is_core' => true],
                ],
            ],

            'Firebase' => [
                'category' => 'Cloud Service',
                'normalized' => 'firebase',
                'aliases' => ['firebase realtime', 'firestore', 'firebase auth', 'google firebase', 'cloud firestore'],
                'levels' => [
                    1 => ['title' => 'Firebase Basics',           'description' => 'Sets up Firebase project and uses Authentication basics.',                                                      'indicators' => ['Firebase Auth', 'email/password login', 'SDK setup']],
                    2 => ['title' => 'Firestore Usage',           'description' => 'Reads and writes data with Firestore, understands collections and documents.',                                  'indicators' => ['Firestore CRUD', 'collections', 'documents', 'real-time listeners']],
                    3 => ['title' => 'Intermediate Firebase',     'description' => 'Uses Firebase Storage, Cloud Functions basics, and security rules.',                                            'indicators' => ['Storage', 'Cloud Functions basics', 'security rules', 'FCM']],
                    4 => ['title' => 'Advanced Firebase',         'description' => 'Designs scalable data models and implements advanced security rules.',                                          'indicators' => ['data modeling', 'advanced rules', 'batch writes', 'transactions']],
                    5 => ['title' => 'Firebase Architecture',     'description' => 'Builds production Firebase backends with cost optimization and monitoring.',                                    'indicators' => ['cost optimization', 'monitoring', 'extensions', 'hosting']],
                ],
                'career_paths' => [
                    'Mobile Developer' => ['required_level' => 2.0, 'weight' => 0.80, 'is_core' => false],
                    'Frontend Developer' => ['required_level' => 1.5, 'weight' => 0.60, 'is_core' => false],
                ],
            ],

            // ──────────────────────────────────────────────────────
            // DevOps / Cloud Engineer Skills
            // ──────────────────────────────────────────────────────

            'Linux' => [
                'category' => 'Operating System',
                'normalized' => 'linux',
                'aliases' => ['unix', 'ubuntu', 'bash', 'shell scripting', 'linux cli', 'centos', 'debian'],
                'levels' => [
                    1 => ['title' => 'Basic Linux Usage',         'description' => 'Navigates the filesystem and runs basic commands.',                                                             'indicators' => ['ls', 'cd', 'mkdir', 'cat', 'chmod']],
                    2 => ['title' => 'Practical Linux',           'description' => 'Manages processes, users, permissions, and writes simple scripts.',                                             'indicators' => ['ps', 'kill', 'chmod/chown', 'cron', 'bash scripts']],
                    3 => ['title' => 'Intermediate Linux',        'description' => 'Manages services, networking basics, and package management.',                                                  'indicators' => ['systemctl', 'netstat', 'apt/yum', 'ssh', 'firewall basics']],
                    4 => ['title' => 'Advanced Linux Admin',      'description' => 'Configures servers, manages volumes, and troubleshoots system issues.',                                         'indicators' => ['LVM', 'log analysis', 'performance tuning', 'security hardening']],
                    5 => ['title' => 'Linux Expert',              'description' => 'Deep kernel knowledge, automates infrastructure, and handles large-scale Linux environments.',                  'indicators' => ['kernel tuning', 'systemd units', 'advanced networking', 'IaC']],
                ],
                'career_paths' => [
                    'DevOps Engineer' => ['required_level' => 3.0, 'weight' => 0.95, 'is_core' => true],
                    'Backend Developer' => ['required_level' => 1.5, 'weight' => 0.60, 'is_core' => false],
                ],
            ],

            'Kubernetes' => [
                'category' => 'Orchestration',
                'normalized' => 'kubernetes',
                'aliases' => ['k8s', 'kube', 'kubectl', 'kubernetes cluster', 'k8s orchestration'],
                'levels' => [
                    1 => ['title' => 'K8s Concepts',              'description' => 'Understands core Kubernetes concepts: pods, nodes, and clusters.',                                             'indicators' => ['pods', 'nodes', 'cluster concepts', 'kubectl basics']],
                    2 => ['title' => 'Basic Deployments',         'description' => 'Deploys applications with Deployments, Services, and ConfigMaps.',                                             'indicators' => ['Deployment', 'Service', 'ConfigMap', 'Namespace']],
                    3 => ['title' => 'Intermediate K8s',          'description' => 'Manages persistent volumes, ingress controllers, and resource limits.',                                         'indicators' => ['PVC/PV', 'Ingress', 'resource limits', 'rolling updates']],
                    4 => ['title' => 'Advanced Kubernetes',       'description' => 'Configures RBAC, Helm charts, horizontal scaling, and observability.',                                         'indicators' => ['RBAC', 'Helm', 'HPA', 'monitoring', 'StatefulSets']],
                    5 => ['title' => 'K8s Architecture',          'description' => 'Designs multi-cluster strategies and manages production-grade Kubernetes environments.',                        'indicators' => ['multi-cluster', 'operators', 'service mesh', 'cluster autoscaling']],
                ],
                'career_paths' => [
                    'DevOps Engineer' => ['required_level' => 3.0, 'weight' => 0.95, 'is_core' => true],
                ],
            ],

            'CI/CD' => [
                'category' => 'DevOps Practice',
                'normalized' => 'cicd',
                'aliases' => ['ci cd', 'continuous integration', 'continuous deployment', 'github actions', 'gitlab ci', 'jenkins', 'circleci'],
                'levels' => [
                    1 => ['title' => 'CI/CD Concepts',            'description' => 'Understands what CI/CD is and why it matters.',                                                                 'indicators' => ['pipeline concept', 'automated testing idea', 'deployment basics']],
                    2 => ['title' => 'Basic Pipelines',           'description' => 'Configures simple pipelines that run tests and build artifacts.',                                               'indicators' => ['GitHub Actions basics', 'build job', 'test job', 'YAML config']],
                    3 => ['title' => 'Practical CI/CD',           'description' => 'Sets up multi-stage pipelines with environment-based deployments.',                                             'indicators' => ['staging/production environments', 'secrets management', 'artifact caching']],
                    4 => ['title' => 'Advanced Pipelines',        'description' => 'Designs pipelines with matrix builds, parallel jobs, and rollback strategies.',                                'indicators' => ['matrix strategy', 'parallel jobs', 'rollback', 'blue/green deploy']],
                    5 => ['title' => 'CI/CD Architecture',        'description' => 'Designs enterprise-grade pipelines with security scanning and full observability.',                             'indicators' => ['SAST/DAST', 'compliance gates', 'GitOps', 'pipeline as code']],
                ],
                'career_paths' => [
                    'DevOps Engineer' => ['required_level' => 3.5, 'weight' => 1.00, 'is_core' => true],
                    'Backend Developer' => ['required_level' => 1.5, 'weight' => 0.60, 'is_core' => false],
                ],
            ],

            'AWS' => [
                'category' => 'Cloud Platform',
                'normalized' => 'aws',
                'aliases' => ['amazon web services', 'amazon aws', 'ec2', 's3', 'lambda', 'aws cloud', 'cloud computing'],
                'levels' => [
                    1 => ['title' => 'AWS Basics',                'description' => 'Understands core AWS concepts and navigates the AWS Console.',                                                  'indicators' => ['IAM basics', 'EC2 launch', 'S3 bucket', 'console navigation']],
                    2 => ['title' => 'Core Services User',        'description' => 'Deploys apps on EC2, uses S3 for storage, and manages basic IAM policies.',                                     'indicators' => ['EC2 deployment', 'S3 management', 'IAM policies', 'security groups']],
                    3 => ['title' => 'Intermediate AWS',          'description' => 'Uses RDS, Lambda, API Gateway, and understands VPC networking.',                                                'indicators' => ['RDS', 'Lambda', 'API Gateway', 'VPC', 'CloudWatch']],
                    4 => ['title' => 'Advanced AWS',              'description' => 'Designs highly available architectures with auto-scaling and cost optimization.',                               'indicators' => ['Auto Scaling', 'Load Balancer', 'CloudFormation', 'cost optimization']],
                    5 => ['title' => 'AWS Architecture',          'description' => 'Architects enterprise AWS solutions with multi-region, disaster recovery, and compliance.',                     'indicators' => ['multi-region', 'disaster recovery', 'Well-Architected Framework', 'CDK']],
                ],
                'career_paths' => [
                    'DevOps Engineer' => ['required_level' => 3.0, 'weight' => 0.95, 'is_core' => true],
                    'Backend Developer' => ['required_level' => 1.5, 'weight' => 0.65, 'is_core' => false],
                ],
            ],

            'Terraform' => [
                'category' => 'Infrastructure as Code',
                'normalized' => 'terraform',
                'aliases' => ['terraform iac', 'hashicorp terraform', 'infrastructure as code', 'iac'],
                'levels' => [
                    1 => ['title' => 'Terraform Basics',          'description' => 'Understands IaC concept and writes basic Terraform configuration.',                                             'indicators' => ['provider block', 'resource block', 'terraform init/plan/apply']],
                    2 => ['title' => 'Basic Infrastructure',      'description' => 'Provisions cloud resources and manages state files.',                                                           'indicators' => ['variables', 'outputs', 'state management', 'tfvars']],
                    3 => ['title' => 'Modular Terraform',         'description' => 'Writes reusable modules and uses remote state backends.',                                                       'indicators' => ['modules', 'remote state', 'data sources', 'locals']],
                    4 => ['title' => 'Advanced Terraform',        'description' => 'Manages complex workspaces, imports existing infrastructure, and applies best practices.',                     'indicators' => ['workspaces', 'import', 'lifecycle rules', 'depends_on']],
                    5 => ['title' => 'Terraform Architecture',    'description' => 'Designs enterprise IaC frameworks with team collaboration and policy enforcement.',                             'indicators' => ['Terraform Cloud', 'Sentinel policies', 'provider development', 'testing']],
                ],
                'career_paths' => [
                    'DevOps Engineer' => ['required_level' => 3.0, 'weight' => 0.90, 'is_core' => true],
                ],
            ],

        ];

        // ═══════════════════════════════════════════════════════════
        // الخطوة 3: إدراج كل شيء في قاعدة البيانات
        // ═══════════════════════════════════════════════════════════
        foreach ($skills as $skillName => $data) {

            // 3-أ: إدراج المهارة
            DB::table('skills')->updateOrInsert(
                ['name' => $skillName],
                [
                    'category' => $data['category'],
                    'normalized_name' => $data['normalized'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $skillId = DB::table('skills')->where('name', $skillName)->value('id');

            // 3-ب: إدراج الـ Aliases
            foreach ($data['aliases'] as $alias) {
                DB::table('skill_aliases')->updateOrInsert(
                    ['Alias' => $alias],
                    [
                        'SkillID' => $skillId,
                        'LanguageCode' => 'en',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 3-ج: إدراج تعريفات المستويات 1-5
            foreach ($data['levels'] as $level => $definition) {
                DB::table('skill_level_definitions')->updateOrInsert(
                    ['SkillID' => $skillId, 'Level' => $level],
                    [
                        'Title' => $definition['title'],
                        'Description' => $definition['description'],
                        'BehavioralIndicatorsJson' => json_encode($definition['indicators']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 3-د: ربط المهارة بالمسارات المهنية
            foreach ($data['career_paths'] as $pathName => $pathData) {
                $careerPathId = DB::table('career_paths')->where('Name', $pathName)->value('CareerPathID');

                if (! $careerPathId) {
                    continue;
                }

                DB::table('career_path_skills')->updateOrInsert(
                    ['CareerPathID' => $careerPathId, 'SkillID' => $skillId],
                    [
                        'RequiredLevel' => $pathData['required_level'],
                        'Weight' => $pathData['weight'],
                        'IsCore' => $pathData['is_core'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}