# حزمة بيانات تقييم تحليل سوق العمل — منصة جسر

## الهدف
هذه الحزمة مخصصة لعرض البيانات التي استُخدمت في تقييم **مصنّف المسار الوظيفي** أمام اللجنة بشكل واضح وقابل للتدقيق.

## الملف الرئيسي
- `Jisr_Market_Analysis_Model_Evaluation_Dataset.xlsx`

ويحتوي على:
1. ملخص اللجنة والمنهجية والنتائج.
2. عينة التطوير: 47 إعلاناً.
3. العينة العمياء: 20 إعلاناً.
4. جدول موحد: 67 إعلاناً.
5. دليل التصنيفات.
6. توثيق الملفات الخام وSHA-256 وأوامر إعادة التشغيل.

## النتائج الرسمية
- Rules Baseline على عينة 47: **26/47 = 55.32% Accuracy**
- Gemini على عينة التطوير 47: **42/47 = 89.36% Accuracy**
- Gemini على العينة العمياء 20: **19/20 = 95.00% Accuracy**
- العينة العمياء النهائية: **20 Gemini direct، 0 fallback، 0 failed requests**

## ملاحظة النزاهة العلمية
بعد التقييم العمياء، تبيّن أن Gemini أعاد `IT Administrator` بصورة صحيحة دلالياً، لكن Mapping الداخلي لم يكن يربطه بمسار DevOps. تم إصلاح الـMapping لاحقاً، لكن النتيجة الرسمية للعينة العمياء بقيت **19/20 = 95%** ولم تُرفع بأثر رجعي.

## الملفات الخام
موجودة داخل مجلد `raw` بهدف إعادة الإنتاج والتحقق.

## أوامر إعادة التقييم
```powershell
php artisan market:evaluate-gemini-classifier --file="app/dataset/تحليل سوق العمل/raw/classifier_evaluation_sample_47_development.csv" --delay-ms=5000

php artisan market:evaluate-gemini-classifier --file="app/dataset/تحليل سوق العمل/raw/classifier_evaluation_sample_20_blind.csv" --delay-ms=5000
```

تم إعداد الحزمة بتاريخ: 2026-07-30
