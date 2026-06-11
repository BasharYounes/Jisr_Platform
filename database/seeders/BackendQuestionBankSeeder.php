<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackendQuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $careerPathId = DB::table('career_paths')
            ->where('Name', 'Backend Developer')
            ->value('CareerPathID');

        if (! $careerPathId) {
            return;
        }

        $questions = $this->questions();

        foreach ($questions as $item) {
            $skillId = DB::table('skills')
                ->where('name', $item['skill'])
                ->value('id');

            if (! $skillId) {
                continue;
            }

            $questionId = DB::table('question_bank')->insertGetId([
                'SkillID' => $skillId,
                'CareerPathID' => $careerPathId,
                'Level' => $item['level'],
                'QuestionType' => $item['question_type'],
                'QuestionText' => $item['question_text'],
                'ExpectedAnswerType' => $item['expected_answer_type'],
                'DifficultyWeight' => $item['difficulty_weight'],
                'IsActive' => true,
                'CreatedByUserId' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'QuestionID');

            foreach ($item['rubrics'] as $index => $rubric) {
                DB::table('question_rubrics')->insert([
                    'QuestionID' => $questionId,
                    'CriterionName' => $rubric['name'],
                    'CriterionDescription' => $rubric['description'],
                    'MaxScore' => $rubric['max_score'],
                    'Weight' => $rubric['weight'],
                    'KeywordsJson' => json_encode($rubric['keywords'], JSON_UNESCAPED_UNICODE),
                    'SampleGoodAnswer' => $rubric['good'],
                    'SampleBadAnswer' => $rubric['bad'],
                    'OrderIndex' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function questions(): array
    {
        return array_merge(
            $this->pythonQuestions(),
            $this->flaskQuestions(),
            $this->sqlQuestions(),
            $this->gitQuestions(),
        );
    }

    private function defaultRubrics(
        string $mainConcept,
        string $exampleConcept,
        string $clarityConcept
    ): array {
        return [
            [
                'name' => 'الفكرة الأساسية',
                'description' => "يوضح المفهوم الأساسي المتعلق بالسؤال: {$mainConcept}.",
                'max_score' => 2,
                'weight' => 1,
                'keywords' => [$mainConcept],
                'good' => "شرح صحيح وواضح للمفهوم الأساسي: {$mainConcept}.",
                'bad' => 'شرح ناقص أو غير صحيح للمفهوم الأساسي.',
            ],
            [
                'name' => 'مثال أو تطبيق عملي',
                'description' => "يقدم مثالًا أو تطبيقًا مناسبًا: {$exampleConcept}.",
                'max_score' => 2,
                'weight' => 1,
                'keywords' => [$exampleConcept],
                'good' => "قدم مثالًا عمليًا مناسبًا: {$exampleConcept}.",
                'bad' => 'لم يقدم مثالًا عمليًا مناسبًا.',
            ],
            [
                'name' => 'الوضوح والدقة',
                'description' => "الإجابة منظمة وواضحة وتتضمن {$clarityConcept}.",
                'max_score' => 1,
                'weight' => 1,
                'keywords' => [$clarityConcept],
                'good' => 'إجابة واضحة ومباشرة ودقيقة.',
                'bad' => 'إجابة مبهمة أو غير منظمة.',
            ],
        ];
    }

    private function pythonQuestions(): array
    {
        return [
            [
                'skill' => 'Python',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بين المتغير والقيمة في Python؟ أعط مثالًا بسيطًا.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('المتغير يشير إلى قيمة', 'x = 5', 'مثال صحيح'),
            ],
            [
                'skill' => 'Python',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما وظيفة جملة if في Python؟ اكتب مثالًا بسيطًا.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('التحقق من شرط', 'if x > 0', 'استخدام شرط صحيح'),
            ],
            [
                'skill' => 'Python',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بين for و while في Python؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.05,
                'rubrics' => $this->defaultRubrics('for للتكرار على عناصر و while تعتمد على شرط', 'حلقة على قائمة أو شرط عداد', 'مقارنة واضحة'),
            ],

            [
                'skill' => 'Python',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'اشرح الفرق بين list و tuple في Python. متى تفضّل استخدام كل منهما؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.20,
                'rubrics' => [
                    [
                        'name' => 'ذكر الفرق الأساسي',
                        'description' => 'يذكر أن list قابلة للتغيير و tuple غير قابلة للتغيير.',
                        'max_score' => 2,
                        'weight' => 1,
                        'keywords' => ['mutable', 'immutable', 'list', 'tuple'],
                        'good' => 'list mutable و tuple immutable.',
                        'bad' => 'ذكر أنهما متشابهان دون توضيح الفرق.',
                    ],
                    [
                        'name' => 'أمثلة استخدام مناسبة',
                        'description' => 'يوضح متى نستخدم list ومتى نستخدم tuple.',
                        'max_score' => 2,
                        'weight' => 1,
                        'keywords' => ['dynamic collection', 'fixed values', 'dictionary key'],
                        'good' => 'list للمجموعات القابلة للتعديل و tuple للثوابت أو الإرجاع الثابت.',
                        'bad' => 'لم يذكر أي سيناريو استخدام.',
                    ],
                    [
                        'name' => 'معلومة إضافية صحيحة',
                        'description' => 'يذكر نقطة مثل الأداء أو الذاكرة أو الثبات.',
                        'max_score' => 1,
                        'weight' => 1,
                        'keywords' => ['performance', 'memory'],
                        'good' => 'tuple أخف أو أسرع نسبيًا في بعض الاستخدامات.',
                        'bad' => 'لا توجد إضافة صحيحة.',
                    ],
                ],
            ],
            [
                'skill' => 'Python',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'كيف تتعامل مع الملفات في Python لقراءة نص من ملف؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.15,
                'rubrics' => $this->defaultRubrics('استخدام open و mode مناسب', 'with open(..., "r")', 'ذكر إغلاق الملف أو with'),
            ],
            [
                'skill' => 'Python',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'اكتب دالة تأخذ قائمة أرقام وتُرجع قائمة جديدة تحتوي فقط على الأرقام الزوجية باستخدام list comprehension.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.25,
                'rubrics' => [
                    [
                        'name' => 'صياغة list comprehension صحيحة',
                        'description' => 'يكتب البنية الصحيحة لـ list comprehension.',
                        'max_score' => 2,
                        'weight' => 1,
                        'keywords' => ['[x for x in nums if x % 2 == 0]'],
                        'good' => 'return [x for x in nums if x % 2 == 0]',
                        'bad' => 'استخدام صياغة غير صحيحة أو ناقصة.',
                    ],
                    [
                        'name' => 'تطبيق شرط الزوجية',
                        'description' => 'يستخدم شرط التحقق من كون الرقم زوجيًا.',
                        'max_score' => 2,
                        'weight' => 1,
                        'keywords' => ['% 2 == 0'],
                        'good' => 'استخدم x % 2 == 0 بشكل صحيح.',
                        'bad' => 'نسي الشرط أو استخدم شرطًا خاطئًا.',
                    ],
                    [
                        'name' => 'وضوح الإجابة',
                        'description' => 'الإجابة قابلة للتنفيذ وواضحة.',
                        'max_score' => 1,
                        'weight' => 1,
                        'keywords' => ['def', 'return'],
                        'good' => 'تعريف دالة مع return واضح.',
                        'bad' => 'إجابة غير مكتملة.',
                    ],
                ],
            ],

            [
                'skill' => 'Python',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'ما هو Exception Handling في Python؟ وكيف تستخدم try و except؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.40,
                'rubrics' => $this->defaultRubrics('منع توقف البرنامج والتعامل مع الأخطاء', 'try/except مع مثال قسمة أو ملف', 'استخدام صحيح للبنية'),
            ],
            [
                'skill' => 'Python',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'اشرح مفهوم OOP في Python واذكر مثالًا على class و object.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('class هو قالب و object نسخة منه', 'تعريف class بسيط وإنشاء object', 'شرح منظم'),
            ],
            [
                'skill' => 'Python',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'ما فائدة كتابة اختبارات بسيطة في Python؟ اذكر مثالًا على اختبار unit test.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('التحقق من صحة الكود ومنع الأعطال', 'assert أو unittest/pytest بسيط', 'ربط الاختبار بسلوك متوقع'),
            ],

            [
                'skill' => 'Python',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'ما هو decorator في Python؟ ومتى قد تستخدمه؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.65,
                'rubrics' => $this->defaultRubrics('تغليف دالة أو تعديل سلوكها', 'logging أو auth أو timing', 'توضيح استخدام @decorator'),
            ],
            [
                'skill' => 'Python',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'اشرح الفرق بين generator و list عادية. متى يكون generator أفضل؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.70,
                'rubrics' => $this->defaultRubrics('generator يولد القيم عند الطلب', 'استهلاك ذاكرة أقل مع البيانات الكبيرة', 'مقارنة دقيقة'),
            ],
            [
                'skill' => 'Python',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'كيف يمكن تحسين أداء سكربت Python بسيط يتعامل مع عدد كبير من السجلات؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.75,
                'rubrics' => $this->defaultRubrics('تحليل السبب قبل التحسين', 'profiling أو generators أو batching', 'ذكر تحسين عملي منطقي'),
            ],

            [
                'skill' => 'Python',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'اشرح متى قد تستخدم asyncio في Python، وما المشكلة التي يحلها؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.00,
                'rubrics' => $this->defaultRubrics('التعامل مع مهام I/O غير المتزامنة', 'requests متعددة أو sockets أو APIs', 'التفريق بين CPU-bound و I/O-bound'),
            ],
            [
                'skill' => 'Python',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'كيف تصمم مكتبة Python صغيرة قابلة لإعادة الاستخدام من قبل مشاريع أخرى؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.05,
                'rubrics' => $this->defaultRubrics('تنظيم modules وواجهة واضحة', 'توثيق واختبارات وتغليف جيد', 'مراعاة maintainability'),
            ],
            [
                'skill' => 'Python',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'ما الاعتبارات المهمة عند تصميم API backend كبيرة باستخدام Python؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.10,
                'rubrics' => $this->defaultRubrics('الهيكلة والقابلية للتوسع والأمان', 'validation/auth/logging/testing', 'رؤية معمارية متماسكة'),
            ],
        ];
    }

    private function flaskQuestions(): array
    {
        return [
            [
                'skill' => 'Flask',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'كيف تنشئ تطبيق Flask بسيط يعرض "Hello World"؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('إنشاء app واستخدام route', 'app = Flask(__name__) و route', 'مثال صحيح يعمل'),
            ],
            [
                'skill' => 'Flask',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما وظيفة @app.route في Flask؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('ربط URL بدالة', 'تعريف route مثل /home', 'شرح مباشر'),
            ],
            [
                'skill' => 'Flask',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'كيف تشغّل تطبيق Flask محليًا أثناء التطوير؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.05,
                'rubrics' => $this->defaultRubrics('تشغيل التطبيق محليًا', 'app.run أو flask run', 'ذكر بيئة التطوير'),
            ],

            [
                'skill' => 'Flask',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'كيف تتعامل مع طلب POST في Flask وتقرأ بيانات JSON من الطلب؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.20,
                'rubrics' => $this->defaultRubrics('استخدام request وقراءة JSON', 'request.get_json()', 'ذكر method POST بوضوح'),
            ],
            [
                'skill' => 'Flask',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بين jsonify و إرجاع string عادي من route في Flask؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.20,
                'rubrics' => $this->defaultRubrics('jsonify يعيد JSON response منظم', 'API response مقابل نص بسيط', 'مقارنة دقيقة'),
            ],
            [
                'skill' => 'Flask',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بين render_template و redirect في Flask؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.25,
                'rubrics' => $this->defaultRubrics('واحد يعرض template والآخر ينقل لمسار آخر', 'مثال صفحة login أو dashboard', 'توضيح الفرق عمليًا'),
            ],

            [
                'skill' => 'Flask',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'ما هي Blueprints في Flask؟ ولماذا نستخدمها؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.40,
                'rubrics' => $this->defaultRubrics('تقسيم التطبيق إلى وحدات منظمة', 'auth blueprint أو api blueprint', 'ربطها بقابلية التوسع'),
            ],
            [
                'skill' => 'Flask',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'كيف تضيف معالجة أخطاء بسيطة في Flask مثل 404 أو 500؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('استخدام error handlers', '@app.errorhandler', 'ذكر سيناريو خطأ واضح'),
            ],
            [
                'skill' => 'Flask',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'كيف تتحقق من صحة بيانات الإدخال في API مبنية بـ Flask؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('التحقق من الحقول قبل المعالجة', 'manual validation أو marshmallow/pydantic-like approach', 'ذكر رسائل خطأ مناسبة'),
            ],

            [
                'skill' => 'Flask',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'كيف تنظّم إعدادات التطبيق في Flask بين بيئة التطوير والإنتاج؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.65,
                'rubrics' => $this->defaultRubrics('فصل config حسب البيئة', 'classes أو env variables', 'ذكر الأمن وعدم hardcode'),
            ],
            [
                'skill' => 'Flask',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'اشرح بشكل مبسط كيف يمكن إضافة authentication إلى API في Flask.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.70,
                'rubrics' => $this->defaultRubrics('التحقق من هوية المستخدم قبل السماح بالوصول', 'JWT أو sessions أو token check', 'ربط ذلك بحماية المسارات'),
            ],
            [
                'skill' => 'Flask',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'ما الأمور التي تجعل تطبيق Flask أقرب لأن يكون جاهزًا للإنتاج؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.75,
                'rubrics' => $this->defaultRubrics('logging و validation و config و error handling', 'gunicorn/env vars/testing', 'نظرة عملية منظمة'),
            ],

            [
                'skill' => 'Flask',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'كيف تصمم Flask service قابلة للتوسع إذا زاد عدد المستخدمين والطلبات؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.00,
                'rubrics' => $this->defaultRubrics('تقليل الترابط وتحسين البنية والتوسع الأفقي', 'caching/queue/db optimization', 'ذكر اعتبارات معمارية'),
            ],
            [
                'skill' => 'Flask',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'ما أهمية الاختبارات في Flask project وكيف تنظمها؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.00,
                'rubrics' => $this->defaultRubrics('ضمان الجودة ومنع regression', 'unit/integration tests مع test client', 'تنظيم ملفات الاختبار'),
            ],
            [
                'skill' => 'Flask',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'إذا كنت تبني API كبيرة بـ Flask، كيف تفصل بين طبقة routes و business logic و data access؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.10,
                'rubrics' => $this->defaultRubrics('فصل المسؤوليات', 'service layer / repository / models', 'تصميم نظيف قابل للصيانة'),
            ],
        ];
    }

    private function sqlQuestions(): array
    {
        return [
            [
                'skill' => 'SQL',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'اكتب استعلام SQL يعرض جميع البيانات من جدول students.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('استخدام SELECT بشكل صحيح', 'SELECT * FROM students', 'صياغة سليمة'),
            ],
            [
                'skill' => 'SQL',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما وظيفة WHERE في SQL؟ أعط مثالًا بسيطًا.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('تصفية الصفوف بناء على شرط', 'WHERE age > 20', 'مثال صحيح'),
            ],
            [
                'skill' => 'SQL',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بين SELECT * و SELECT column_name؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.05,
                'rubrics' => $this->defaultRubrics('اختيار كل الأعمدة مقابل أعمدة محددة', 'تقليل البيانات المرجعة', 'مقارنة واضحة'),
            ],

            [
                'skill' => 'SQL',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'اشرح ما هو JOIN في SQL ولماذا نستخدمه.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.20,
                'rubrics' => $this->defaultRubrics('ربط بيانات من أكثر من جدول', 'students مع enrollments', 'ذكر علاقة بين الجداول'),
            ],
            [
                'skill' => 'SQL',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بين WHERE و HAVING؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.25,
                'rubrics' => $this->defaultRubrics('WHERE قبل التجميع و HAVING بعد GROUP BY', 'فلترة rows مقابل grouped results', 'مقارنة صحيحة'),
            ],
            [
                'skill' => 'SQL',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'كيف تستخدم GROUP BY مع COUNT لحساب عدد الطلبات لكل مستخدم؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.25,
                'rubrics' => $this->defaultRubrics('تجميع حسب المستخدم', 'COUNT(*) مع GROUP BY user_id', 'صياغة منطقية'),
            ],

            [
                'skill' => 'SQL',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'ما هو subquery في SQL؟ ومتى قد تحتاجه؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.40,
                'rubrics' => $this->defaultRubrics('استعلام داخل استعلام', 'استخدامه للفلترة أو المقارنة', 'مثال مفهوم'),
            ],
            [
                'skill' => 'SQL',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'ما المقصود بتطبيع الجداول (Normalization) بشكل مبسط؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('تقليل التكرار وتحسين الاتساق', 'فصل البيانات في جداول مناسبة', 'ربط الفكرة بتصميم قاعدة البيانات'),
            ],
            [
                'skill' => 'SQL',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'كيف تكتب استعلامًا يحتوي على أكثر من شرط باستخدام AND و OR دون الوقوع في أخطاء منطقية؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('فهم ترتيب الشروط', 'استخدام الأقواس عند الحاجة', 'مثال صحيح واضح'),
            ],

            [
                'skill' => 'SQL',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'ما فائدة index في قواعد البيانات العلائقية؟ وما أثره على الأداء؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.65,
                'rubrics' => $this->defaultRubrics('تسريع القراءة والبحث', 'عمود كثير الاستخدام في WHERE/JOIN', 'الإشارة إلى tradeoff في الكتابة'),
            ],
            [
                'skill' => 'SQL',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'كيف تفسّر بطء استعلام SQL بشكل مبدئي؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.70,
                'rubrics' => $this->defaultRubrics('فحص execution plan أو indexes أو حجم البيانات', 'تحليل WHERE/JOIN/SELECT *', 'ذكر خطوات عملية'),
            ],
            [
                'skill' => 'SQL',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'ما الأخطاء الشائعة التي قد تجعل استعلام SQL غير فعال؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.70,
                'rubrics' => $this->defaultRubrics('SELECT * أو غياب indexes أو joins غير منضبطة', 'مثال على استعلام غير فعال', 'وعي بالأداء'),
            ],

            [
                'skill' => 'SQL',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'كيف تصمم schema جيدة لتطبيق Backend يدير مستخدمين وطلبات ومنتجات؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.00,
                'rubrics' => $this->defaultRubrics('تحديد الجداول والعلاقات بوضوح', 'users/orders/products/order_items', 'مراعاة التطبيع والوضوح'),
            ],
            [
                'skill' => 'SQL',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'ما الاعتبارات المهمة عند تحسين استعلامات وتقليل الحمل على قاعدة البيانات في نظام كبير؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.05,
                'rubrics' => $this->defaultRubrics('تحسين الاستعلامات والفهارس والكاش', 'pagination/caching/indexing', 'رؤية شاملة متوازنة'),
            ],
            [
                'skill' => 'SQL',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'كيف توازن بين سهولة تصميم قاعدة البيانات وبين الأداء وقابلية التوسع؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.10,
                'rubrics' => $this->defaultRubrics('التوازن بين normalization والأداء', 'أمثلة على tradeoffs', 'شرح معماري ناضج'),
            ],
        ];
    }

    private function gitQuestions(): array
    {
        return [
            [
                'skill' => 'Git',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما فائدة Git بشكل عام؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('إدارة الإصدارات وتتبع التغييرات', 'الرجوع لنسخة سابقة أو العمل الجماعي', 'شرح صحيح'),
            ],
            [
                'skill' => 'Git',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بين git add و git commit؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.05,
                'rubrics' => $this->defaultRubrics('add يجهز التعديلات و commit يحفظها في السجل', 'staging area ثم commit', 'مقارنة واضحة'),
            ],
            [
                'skill' => 'Git',
                'level' => 1,
                'question_type' => 'open_text',
                'question_text' => 'ما وظيفة git push؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.00,
                'rubrics' => $this->defaultRubrics('رفع التغييرات إلى remote repository', 'GitHub أو remote server', 'ذكر أنه بعد commit غالبًا'),
            ],

            [
                'skill' => 'Git',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'لماذا نستخدم branches في Git؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.20,
                'rubrics' => $this->defaultRubrics('عزل التغييرات وتطوير ميزات مستقلة', 'feature branch', 'ربطها بالعمل الآمن'),
            ],
            [
                'skill' => 'Git',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'اشرح بشكل مبسط ما الذي يفعله git merge.',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.20,
                'rubrics' => $this->defaultRubrics('دمج تاريخ أو تغييرات فرعين', 'دمج feature branch مع main', 'شرح واضح'),
            ],
            [
                'skill' => 'Git',
                'level' => 2,
                'question_type' => 'open_text',
                'question_text' => 'ما المقصود بـ merge conflict؟ وكيف تتعامل معه بشكل مبدئي؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.25,
                'rubrics' => $this->defaultRubrics('تعارض بين تعديلات على نفس الجزء', 'تعديل الملف ثم commit', 'ذكر المراجعة اليدوية'),
            ],

            [
                'skill' => 'Git',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'ما الفكرة العامة وراء Pull Request أو Merge Request في العمل الجماعي؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.40,
                'rubrics' => $this->defaultRubrics('طلب مراجعة قبل الدمج', 'review + approval + merge', 'ربطه بجودة الكود والتعاون'),
            ],
            [
                'skill' => 'Git',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'ما الفرق بشكل مبسط بين merge و rebase؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('merge يحافظ على التاريخ المتشعب و rebase يعيد ترتيب التاريخ', 'تاريخ أنظف في rebase', 'مقارنة بدون خلط'),
            ],
            [
                'skill' => 'Git',
                'level' => 3,
                'question_type' => 'open_text',
                'question_text' => 'كيف تحافظ على commit history نظيف ومفهوم في مشروع جماعي؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.45,
                'rubrics' => $this->defaultRubrics('commits واضحة وصغيرة ومنطقية', 'رسائل commit جيدة أو squash', 'وعي بالتعاون'),
            ],

            [
                'skill' => 'Git',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'كيف تتعامل مع تعارضات merge معقدة في مشروع كبير؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.65,
                'rubrics' => $this->defaultRubrics('فهم التغييرات قبل الدمج', 'pull latest / resolve carefully / test', 'منهجية آمنة'),
            ],
            [
                'skill' => 'Git',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'ما الفائدة من وجود workflow واضح للفروع مثل feature branches و release branches؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.70,
                'rubrics' => $this->defaultRubrics('تنظيم العمل وتقليل الفوضى', 'feature/release/hotfix workflow', 'ربطها بالإصدار والجودة'),
            ],
            [
                'skill' => 'Git',
                'level' => 4,
                'question_type' => 'open_text',
                'question_text' => 'متى قد يكون force push خطرًا؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 1.70,
                'rubrics' => $this->defaultRubrics('قد يغيّر التاريخ ويحذف عمل الآخرين', 'بعد rebase على branch مشترك', 'ذكر الحذر والبدائل'),
            ],

            [
                'skill' => 'Git',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'كيف تضع استراتيجية Git مناسبة لفريق Backend يعمل على ميزات متعددة بالتوازي؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.00,
                'rubrics' => $this->defaultRubrics('تحديد workflow واضح للفروع والمراجعة', 'main/develop/feature/release أو trunk-based', 'رؤية تنظيمية متماسكة'),
            ],
            [
                'skill' => 'Git',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'إذا حدثت مشكلة في تاريخ المستودع بعد rebase أو force push، كيف تتعامل معها بحذر؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.05,
                'rubrics' => $this->defaultRubrics('التعامل بحذر واسترجاع السجل', 'reflog أو branch backup أو التنسيق مع الفريق', 'سلامة البيانات أولًا'),
            ],
            [
                'skill' => 'Git',
                'level' => 5,
                'question_type' => 'open_text',
                'question_text' => 'ما الممارسات التي تجعل استخدام Git على مستوى الفريق احترافيًا وقابلًا للتوسع؟',
                'expected_answer_type' => 'text',
                'difficulty_weight' => 2.10,
                'rubrics' => $this->defaultRubrics('سياسات واضحة ومراجعات ورسائل commits ومعايير دمج', 'branch protection/PR reviews/CI', 'نظرة ناضجة للعمل الجماعي'),
            ],
        ];
    }
}
