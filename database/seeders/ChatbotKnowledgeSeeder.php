<?php

namespace Database\Seeders;

use App\Models\ChatbotKnowledgeEntry;
use Illuminate\Database\Seeder;

class ChatbotKnowledgeSeeder extends Seeder
{
    /**
     * Seed only the ten approved Jisr platform-help knowledge entries.
     */
    public function run(): void
    {
        $entries = [
            [
                'key' => 'platform_overview',
                'category' => 'platform_help',
                'question_ar' => 'ما هي منصة جسر؟',
                'question_en' => 'What is Jisr Platform?',
                'answer_ar' => 'منصة جسر هي منصة رقمية تربط الطالب بسوق العمل، وتجمع الطلاب والشركات والمشرفين والمرشدين في مكان واحد. تساعد الطالب على بناء ملفه المهني، تحليل سيرته الذاتية، تحديد مستواه، المشاركة في المشاريع، والتقدم إلى فرص العمل والتدريب المناسبة.',
                'answer_en' => 'Jisr is a digital platform that connects students with the labor market and brings students, companies, supervisors, and mentors together in one place. It helps students build professional profiles, analyze CVs, determine skill levels, participate in projects, and apply for suitable jobs and internships.',
                'keywords' => [
                    'ar' => ['منصة جسر', 'ما هي جسر', 'ما هي منصة جسر', 'شو هي جسر', 'تعريف منصة جسر'],
                    'en' => ['Jisr Platform', 'what is Jisr', 'what is Jisr Platform', 'about Jisr'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'cv_upload',
                'category' => 'platform_help',
                'question_ar' => 'كيف أرفع السيرة الذاتية؟',
                'question_en' => 'How do I upload my CV?',
                'answer_ar' => 'يمكنك رفع السيرة الذاتية من قسم السيرة الذاتية داخل ملفك المهني في تطبيق الطالب. اختر ملف السيرة ثم أرسله، وبعد نجاح الرفع يحلل النظام السيرة ويستخرج المهارات الموجودة فيها.',
                'answer_en' => 'You can upload your CV from the CV section in your professional profile within the student app. Select the CV file and submit it. After a successful upload, the system analyzes the CV and extracts the skills found in it.',
                'keywords' => [
                    'ar' => ['رفع السيرة الذاتية', 'كيف ارفع السيرة الذاتية', 'ارفع السيرة', 'تحميل السيرة الذاتية', 'رفع cv', 'ارفع cv', 'سي في'],
                    'en' => ['upload CV', 'how to upload CV', 'upload resume', 'CV upload', 'resume upload'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'take_level_assessment',
                'category' => 'platform_help',
                'question_ar' => 'كيف أجري اختبار تحديد المستوى؟',
                'question_en' => 'How do I take the level assessment?',
                'answer_ar' => 'افتح قسم اختبار تحديد المستوى في تطبيق الطالب، ثم ابدأ الاختبار المتاح وأجب عن الأسئلة حتى النهاية. بعد إكمال الاختبار يعالج النظام إجاباتك ويعرض النتيجة والمستوى المسجل لك.',
                'answer_en' => 'Open the level assessment section in the student app, start the available assessment, and answer all questions. After completion, the system processes your answers and displays your result and recorded level.',
                'keywords' => [
                    'ar' => ['اختبار تحديد المستوى', 'اجراء اختبار تحديد المستوى', 'كيف اعمل الاختبار', 'كيف اقدم الاختبار', 'ابدأ الاختبار', 'وين الاختبار'],
                    'en' => ['level assessment', 'take the assessment', 'start assessment', 'how to take assessment', 'where is the assessment'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'apply_to_opportunity',
                'category' => 'platform_help',
                'question_ar' => 'كيف أتقدم إلى فرصة؟',
                'question_en' => 'How do I apply for an opportunity?',
                'answer_ar' => 'افتح قسم الفرص في تطبيق الطالب، واختر الفرصة المناسبة، ثم راجع الوصف والمتطلبات واضغط على خيار التقديم. بعد إرسال الطلب يمكنك متابعة حالته من قسم طلباتي.',
                'answer_en' => 'Open the opportunities section in the student app, choose a suitable opportunity, review its description and requirements, and select the apply option. After submitting, you can follow the application status from the My Applications section.',
                'keywords' => [
                    'ar' => ['التقدم الى فرصة', 'التقديم على فرصة', 'كيف اقدم على فرصة', 'كيف بقدم على فرصة', 'التقديم على تدريب', 'اقدم على تدريب'],
                    'en' => ['apply for opportunity', 'apply to opportunity', 'how to apply', 'apply for internship', 'opportunity application'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'join_project',
                'category' => 'platform_help',
                'question_ar' => 'كيف أنضم إلى مشروع؟',
                'question_en' => 'How do I join a project?',
                'answer_ar' => 'افتح قسم المشاريع في تطبيق الطالب، واختر مشروعًا مناسبًا لمستواك، ثم راجع المتطلبات وأرسل طلب الانضمام. بعد ذلك يمكنك متابعة حالة الطلب حتى تتم مراجعته.',
                'answer_en' => 'Open the projects section in the student app, choose a project suitable for your level, review its requirements, and submit a join request. You can then follow the request status while it is being reviewed.',
                'keywords' => [
                    'ar' => ['الانضمام الى مشروع', 'كيف انضم الى مشروع', 'كيف انضم لمشروع', 'التقديم على مشروع', 'طلب انضمام مشروع'],
                    'en' => ['join project', 'how to join a project', 'project join request', 'apply to project'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'project_task_opportunity_difference',
                'category' => 'platform_help',
                'question_ar' => 'ما الفرق بين المشروع والمهمة والفرصة؟',
                'question_en' => 'What is the difference between a project, a task, and an opportunity?',
                'answer_ar' => 'المشروع عمل عملي متكامل ينفذه الطالب خلال مدة محددة وتحت إشراف، ويتضمن متطلبات ونتيجة نهائية متوقعة. المهمة عمل أصغر وأكثر تحديدًا وقد تكون جزءًا من مشروع أو مهمة قصيرة تنشرها شركة. أما الفرصة فهي إعلان عن تدريب أو وظيفة يمكن للطالب التقدم إليها.',
                'answer_en' => 'A project is a complete practical assignment completed within a defined period under supervision, with requirements and an expected final outcome. A task is smaller and more specific, and may be part of a project or a short task published by a company. An opportunity is a job or internship posting that a student can apply for.',
                'keywords' => [
                    'ar' => ['الفرق بين المشروع والمهمة والفرصة', 'شو الفرق بين المشروع والمهمة والفرصة', 'مشروع مهمة فرصة', 'مشروع ومهمة وفرصة'],
                    'en' => ['difference between project task opportunity', 'project vs task vs opportunity', 'project task opportunity'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'student_evaluation',
                'category' => 'platform_help',
                'question_ar' => 'كيف يتم تقييم الطالب؟',
                'question_en' => 'How is the student evaluated?',
                'answer_ar' => 'يقيّم المشرف أداء الطالب وفق معايير مرتبطة بالمشروع، مثل جودة العمل والالتزام والتواصل. يسجل المشرف الدرجات والملاحظات في النظام، ثم تظهر نتيجة التقييم للطالب عندما تصبح متاحة.',
                'answer_en' => 'The supervisor evaluates the student using project-related criteria such as work quality, commitment, and communication. The supervisor records scores and comments in the system, and the evaluation result becomes visible to the student when it is available.',
                'keywords' => [
                    'ar' => ['تقييم الطالب', 'كيف يتم تقييم الطالب', 'كيف بينقيم الطالب', 'كيف يتم التقييم', 'معايير التقييم'],
                    'en' => ['student evaluation', 'how is the student evaluated', 'how evaluation works', 'evaluation criteria'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'supervisor_role',
                'category' => 'platform_help',
                'question_ar' => 'ما دور المشرف؟',
                'question_en' => 'What is the supervisor’s role?',
                'answer_ar' => 'يتابع المشرف تقدم الطالب في المشروع، ويوزع المهام عند الحاجة، ويقدم التوجيه والملاحظات، ثم يقيّم أداء الطالب وفق معايير المشروع.',
                'answer_en' => 'The supervisor follows the student’s project progress, assigns tasks when needed, provides guidance and feedback, and evaluates the student according to the project criteria.',
                'keywords' => [
                    'ar' => ['دور المشرف', 'ما دور المشرف', 'شو دور المشرف', 'ماذا يفعل المشرف', 'مهام المشرف'],
                    'en' => ['supervisor role', 'what does the supervisor do', 'supervisor responsibilities'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'request_and_project_statuses',
                'category' => 'platform_help',
                'question_ar' => 'ما معنى حالات الطلبات والمشاريع؟',
                'question_en' => 'What do application and project statuses mean?',
                'answer_ar' => 'توضح الحالة المرحلة الحالية للطلب أو المشروع. في الطلبات، تعني Pending أن الطلب قيد المراجعة، وAccepted أنه مقبول، وRejected أنه مرفوض. في المشاريع، تعني Assigned أن المشروع أُسند، وIn Progress أنه قيد التنفيذ، وUnder Review أنه قيد المراجعة، وCompleted أنه مكتمل، وCancelled أنه ملغى.',
                'answer_en' => 'A status shows the current stage of an application or project. For applications, Pending means under review, Accepted means approved, and Rejected means declined. For projects, Assigned means the project has been assigned, In Progress means work is ongoing, Under Review means it is being reviewed, Completed means finished, and Cancelled means cancelled.',
                'keywords' => [
                    'ar' => ['حالات الطلبات', 'حالات المشاريع', 'معنى الحالة', 'شو يعني pending', 'شو يعني accepted', 'شو يعني rejected', 'شو يعني under review'],
                    'en' => ['application statuses', 'project statuses', 'status meaning', 'what does pending mean', 'what does under review mean'],
                ],
                'action' => null,
                'is_active' => true,
            ],
            [
                'key' => 'find_assessment_or_evaluation_result',
                'category' => 'platform_help',
                'question_ar' => 'أين أجد نتيجة الاختبار أو التقييم؟',
                'question_en' => 'Where can I find my assessment or evaluation result?',
                'answer_ar' => 'يمكنك العثور على نتيجة اختبار تحديد المستوى من قسم الاختبارات أو النتائج في تطبيق الطالب. أما تقييم المشروع فتجده ضمن تفاصيل المشروع أو قسم التقييمات عندما يصبح التقييم متاحًا.',
                'answer_en' => 'You can find the level assessment result in the assessments or results section of the student app. A project evaluation can be found in the project details or evaluations section once the evaluation is available.',
                'keywords' => [
                    'ar' => ['نتيجة الاختبار', 'نتيجة التقييم', 'اين اجد النتيجة', 'وين النتيجة', 'وين بلاقي النتيجة', 'كيف اشوف النتيجة'],
                    'en' => ['assessment result', 'evaluation result', 'where is the result', 'where can I see my result', 'find assessment result'],
                ],
                'action' => null,
                'is_active' => true,
            ],
        ];

        foreach ($entries as $entry) {
            ChatbotKnowledgeEntry::query()->updateOrCreate(
                ['key' => $entry['key']],
                $entry,
            );
        }
    }
}
