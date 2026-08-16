<?php

return [
    /*
     * اسم نسخة خوارزمية التصنيف.
     * يفيدنا لاحقاً إذا غيّرنا الأوزان أو المنطق.
     */
    'method' => 'weighted_rules_v1',

    /*
     * المهارة الأساسية أقوى من المهارة المساعدة.
     *
     * مثال:
     * Flutter مهارة Core لمسار Mobile.
     * Firebase مهارة مساعدة وليست دليلاً كافياً وحدها.
     */
    'core_skill_multiplier' => 2.0,

    /*
     * شروط قبول التصنيف.
     */
    'thresholds' => [
        /*
         * أقل مجموع نقاط يسمح بالتصنيف.
         */
        'minimum_score' => 2.5,

        /*
         * يجب أن يتفوق المسار الأول على الثاني
         * بهذا الفارق على الأقل.
         */
        'minimum_margin' => 1.0,

        /*
         * المسار الأول يجب أن يكون أقوى من الثاني
         * بهذه النسبة على الأقل.
         */
        'minimum_ratio' => 1.25,
    ],

    /*
     * إشارات قوية موجودة في عنوان الوظيفة.
     *
     * لا نجمع جميع الإشارات المتطابقة للمسار نفسه؛
     * نأخذ أعلى إشارة فقط، لمنع تضخيم النقاط.
     */
    'paths' => [
        'Backend Developer' => [
            'backend developer' => 4.0,
            'backend engineer' => 4.0,
            'back end developer' => 4.0,
            'server side developer' => 3.5,
            'api developer' => 3.5,
            'laravel developer' => 4.0,
            'php developer' => 3.5,
            'python backend developer' => 4.0,
            'nodejs developer' => 3.5,
            'node js developer' => 3.5,
            'dotnet developer' => 3.5,
            'aspnet developer' => 4.0,
            'backend entwickler' => 4.0,
            'backend softwareentwickler' => 4.0,
            'server entwickler' => 3.5,

            'مطور باك اند' => 4.0,
            'مهندس باك اند' => 4.0,
            'مطور برمجيات خلفية' => 3.5,
        ],

        'Frontend Developer' => [
            'frontend developer' => 4.0,
            'frontend engineer' => 4.0,
            'front end developer' => 4.0,
            'ui developer' => 3.5,
            'react developer' => 4.0,
            'vue developer' => 4.0,
            'angular developer' => 4.0,
            'web interface developer' => 3.5,
            'frontend entwickler' => 4.0,
            'frontend ui entwickler' => 4.0,
            'ui entwickler' => 3.5,

            'مطور فرونت اند' => 4.0,
            'مطور واجهات' => 4.0,
            'مهندس واجهات' => 4.0,
        ],

        'Mobile Developer' => [
            'mobile developer' => 4.0,
            'mobile engineer' => 4.0,
            'flutter developer' => 4.5,
            'android developer' => 4.0,
            'ios developer' => 4.0,
            'react native developer' => 4.0,
            'mobile entwickler' => 4.0,
            'flutter entwickler' => 4.5,
            'android entwickler' => 4.0,
            'ios entwickler' => 4.0,

            'مطور تطبيقات موبايل' => 4.0,
            'مطور فلاتر' => 4.5,
            'مطور اندرويد' => 4.0,
            'مطور تطبيقات ios' => 4.0,
        ],

        'DevOps Engineer' => [
            'devops engineer' => 4.5,
            'devops developer' => 4.0,
            'site reliability engineer' => 4.5,
            'sre engineer' => 4.0,
            'cloud engineer' => 4.0,
            'platform engineer' => 3.5,
            'infrastructure engineer' => 4.0,
            'kubernetes engineer' => 4.0,
            'devops ingenieur' => 4.5,
            'cloud ingenieur' => 4.0,
            'plattform ingenieur' => 3.5,
            'infrastruktur ingenieur' => 4.0,
            'cloud architect' => 4.0,
            'cloud transformation architect' => 4.0,
            'microsoft cloud architect' => 4.0,
            'azure cloud architect' => 4.0,

            'cloud architekt' => 4.0,
            'cloud transformationsarchitekt' => 4.0,
            'system administrator' => 4.0,
            'systems administrator' => 4.0,
            'systemadministrator' => 4.0,

            'cloud native storage engineer' => 4.5,
            'storage engineer' => 4.0,
            'ceph engineer' => 4.0,
            'cloud platform architect' => 4.0,

            'systemadministrator linux' => 4.0,
            'cloud speicher ingenieur' => 4.0,

            'مسؤول انظمة' => 4.0,
            'مدير انظمة' => 4.0,
            'مهندس تخزين سحابي' => 4.0,

            'مهندس معماري سحابي' => 4.0,
            'معماري حلول سحابية' => 4.0,

            'مهندس ديف اوبس' => 4.5,
            'مهندس سحابة' => 4.0,
            'مهندس بنية تحتية' => 4.0,
        ],
    ],
    /*
     * Titles that explicitly combine more than one
     * supported career path.
     */
    'ambiguous_title_signals' => [
        'full stack',
        'fullstack',
        'full stack developer',
        'full stack engineer',
        'full stack entwickler',
        'fullstack entwickler',

        'مطور فل ستاك',
        'مهندس فل ستاك',
        'مطور واجهات وخلفية',
    ],


    /*
     * هذه الإشارات تستخدم فقط عندما لا توجد
     * أدلة تقنية كافية.
     */
    'out_of_scope_title_signals' => [
        'sales manager',
        'sales representative',
        'marketing manager',
        'digital marketer',
        'human resources',
        'hr manager',
        'recruiter',
        'accountant',
        'finance manager',
        'customer service',
        'warehouse worker',
        'nurse',
        'doctor',
        // Technical tracks not currently supported.
        'data science',
        'data scientist',
        'machine learning',
        'machine learning engineer',
        'ml engineer',
        'ai engineer',
        'edge ai',
        'artificial intelligence',
        'quality assurance',
        'qa engineer',
        'test engineer',
        'verification engineer',
        'verification',

        // Management and non-development roles.
        'engineering manager',
        'project engineering manager',
        'project manager',
        'account director',
        'account manager',
        'people ops',
        'payroll',
        'manufacturing',
        'online marketing',
        'media design',

        // German signals.
        'projektleitung',
        'projektlekitung',
        'personalakten',
        'personalwesen',
        'lohnabrechnung',
        'fertigung',
        'verifikation',
        // Data and analytics tracks not currently supported.
        'data engineer',
        'azure data engineer',
        'analytics engineer',
        'business intelligence',
        'bi developer',

        // IT roles not represented by the four supported paths.
        'it support',
        'it support specialist',
        'technical support',
        'support specialist',
        'help desk',
        'service desk',
        'sap consultant',
        'sap inhouse consultant',

        // Project and operational management.
        'it project manager',
        'software project manager',
        'technical director',
        'production planning',
        'maintenance technician',
        'facility management',

        // German HR roles.
        'personalsachbearbeiter',
        'personalreferent',
        'zeitwirtschaft',
        'arbeitnehmerüberlassung',

        // German customer-service roles.
        'kundenberater',
        'kundenbetreuer',
        'telefonischer kundenberater',

        // German operational and industrial roles.
        'technischer leiter',
        'instandhaltung',
        'halbleiterfertigung',
        'auftragskoordinator',
        'produktionsplanung',
        'technischer sachbearbeiter',
        'disposition',
        'haustechniker',
        'gebäudemanagement',
        'gebäudetechnik',
        'immobilienverwaltung',

        // German IT roles outside the supported paths.
        'it projektmanager',
        'sap inhouse consultant',
        'it support specialist',
        // AI specializations not currently supported.
        'microsoft ai',
        'ai copilot engineer',
        'copilot engineer',
        'microsoft copilot engineer',

        // SAP specializations not currently supported.
        'sap btp',
        'sap btp developer',

        // Systems, integration and embedded specializations.
        'system engineer integration',
        'system ingenieur integration',
        'integration tests',
        'funkkommunikationssysteme',

        // Desktop and embedded C++ roles.
        'softwareentwickler c++ qt',
        'c++ qt',
        'qt developer',

        // Arabic equivalents.
        'مهندس ذكاء اصطناعي',
        'مهندس تكامل انظمة',
        'مهندس اختبارات انظمة',
        'مطور كيو تي',

        // Arabic equivalents.
        'مهندس بيانات',
        'محلل بيانات',
        'دعم فني',
        'مكتب المساعدة',
        'استشاري ساب',
        'مدير مشروع تقني',
        'مدير تقني',
        'صيانة',
        'تخطيط الإنتاج',
        'خدمة العملاء',

        // Arabic signals.
        'علوم البيانات',
        'عالم بيانات',
        'تعلم الآلة',
        'ذكاء اصطناعي',
        'ضمان الجودة',
        'مختبر برمجيات',
        'مدير هندسي',
        'مدير مشروع',
        'موارد بشرية',
        'رواتب',
        'تسويق',
        'محاسبة',
    ],
];
