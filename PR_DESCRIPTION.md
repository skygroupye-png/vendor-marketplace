# feat(vendors): implement vendor approval workflow and audit history

## الملخص

يضيف هذا الـ PR نظام موافقة البائعين الجديد مع الحفاظ على فصل المسؤوليات وتحسين قابلية الصيانة، دون تغيير واجهة المستخدم الحالية.

## التغييرات

### Backend
- ✅ إضافة `VendorStatus` كثوابت (`final class`).
- ✅ إضافة `VendorApprovalService`.
- ✅ معاملات قاعدة البيانات (Transactions) للموافقة والرفض.
- ✅ منح دور `vmp_vendor` فقط بعد الموافقة.
- ✅ إطلاق الأحداث:
  - `vmp.vendor.approved`
  - `vmp.vendor.rejected`

### Database
- ✅ Migration آمن (Idempotent).
- ✅ إضافة أعمدة:
  - `status`
  - `approved_at`
  - `approved_by`
  - `rejected_at`
  - `rejected_by`
  - `reject_reason`
- ✅ إنشاء جدول `vmp_vendor_history`.

### Upgrade
- ✅ Upgrade Runner بإصدار قاعدة بيانات.
- ✅ Migration Lock مع Timeout (10 دقائق).
- ✅ تسجيل نجاح وفشل الترقية في اللوق (error_log) وإطلاق أحداث:
  - `vmp.upgrade.success`
  - `vmp.upgrade.failed`

### Testing
- ✅ Integration Tests.
- ✅ PHPUnit.
- ✅ CI (GitHub Actions) — يشمل: PHP syntax, Composer validate, PHPCS (WordPress), PHPStan، واختبارات Unit & Integration.
- ✅ رفع تقارير JUnit وArtifacts عند الانتهاء.

---

## Security Considerations

- عمليات الموافقة والرفض مُقيّدة للمسؤولين المصرح لهم (capability checks ستُطبّق في PR#2 عند إضافة واجهة الإدارة أو عند تنفيذ endpoints).
- تحديثات قاعدة البيانات تُغلف بمعاملات (Transactions) لضمان الاتساق.
- UpgradeRunner يستخدم قفلًا مع مهلة لمنع تشغيل الترقية المتزامنة على مواقع ذات زيارات عالية.
- لا يتم تعيين دور البائع أثناء التسجيل؛ تُمنَح الأدوار فقط بعد موافقة صريحة.

---

## ملاحظات مهمة

- **يتطلب تنفيذ Migration قبل النشر للإنتاج.**
- يوصى بتشغيل Migration يدوياً على Staging أولاً ومراقبة الـ logs.
- يجب أخذ نسخة احتياطية من قاعدة البيانات قبل الترقية.
- UpgradeRunner سيحاول تشغيل المهاجرات تلقائياً عند تحميل الإضافة، لكنه يُفضل التحقق يدوياً على Staging.

---

## Rollback

If deployment must be reverted:

1. Revert the application commit(s).
2. Restore the database from backup if the migration has already been executed.
3. Verify that no partially migrated schema remains.

> ملاحظة: لا يوجد down migration آلي في هذه النسخة لتفادي خسارة البيانات؛ لذلك استعادة قاعدة البيانات من النسخة الاحتياطية هي الطريقة الموصى بها للـ rollback.

---

## Checklist (يجب التحقق منها على Staging/CI قبل الدمج)

- [ ] Migration تم تشغيله على Staging.
- [ ] تمت مراجعة الأعمدة الجديدة.
- [ ] تم إنشاء جدول `vmp_vendor_history`.
- [ ] تسجيل بائع جديد بحالة `pending`.
- [ ] الموافقة تضيف دور `vmp_vendor`.
- [ ] الرفض يسجل السبب وسجل التاريخ.
- [ ] الأحداث تعمل بصورة صحيحة (`vmp.vendor.approved`, `vmp.vendor.rejected`).
- [ ] جميع اختبارات CI نجحت (Code Quality + Unit + Integration).
- [ ] تمت مراجعة الكود ولا توجد ملاحظات حرجة.

---

## خارج نطاق هذا PR

لن يتضمن هذا الـ PR:
- واجهة إدارة الموافقة/الرفض.
- AJAX Endpoints.
- Email Listeners.
- صفحة Activity Timeline.

ستُنجز هذه الميزات في **PR#2**.

---

## Future Work (PR#2)

- Admin Approval UI
- Reject Reason Modal
- Protected AJAX/REST endpoints
- Email listeners
- Activity timeline
- Notification templates

---

## Breaking Changes

لا توجد تغييرات تكسر التوافق البرمجي (Backward Compatible) في الكود نفسه، لكن هناك شرط إلزامي واحد قبل استخدام الإصدار الجديد:
- يجب تشغيل Migration (UpgradeRunner) قبل استخدام ميزات الموافقة في الإنتاج. عدم تشغيلها سيمنع تسجيل الحقول الجديدة وسجلات التاريخ من العمل بشكل كامل.

---

## كيفية اختبار سريع (ملاحظات للمراجع / QA)

1. أخذ نسخة احتياطية للـ DB.
2. تحديث/سحب الفرع `feature/vendor-approval-workflow` على Staging.
3. تشغيل المهاجرات يدوياً (اختياري لكن موصى به) عبر:
   wp eval "require 'wp-load.php'; \\VMP\\Database\\Migrations\\V2_AddVendorApprovalColumns::up();"
4. تأكد من وجود الأعمدة ووجود جدول `vmp_vendor_history`.
5. سجّل مستخدماً جديداً عبر واجهة التسجيل → تحقّق أن السجل أنشئ بحالة `pending`.
6. استدعي الموافقة برمجياً (أو لاحقًا عبر Admin UI) وتحقّق من:
   - `status = approved`
   - `approved_at`, `approved_by` مضبوطان
   - المستخدم لديه `vmp_vendor` (تمت إضافة الدور عبر `add_role`)
   - إدخال سجل في `vmp_vendor_history`
   - حدث `vmp.vendor.approved` تم إطلاقه
7. شغّل Integration Tests عبر PHPUnit والاطّلاع على تقرير JUnit الناتج في الـ CI artifacts.

---

إذا كان هذا النص نهائيًا فأنت جاهز لفتح الـ PR؛ انسخ هذا المحتوى إلى وصف الـ PR عند الإنشاء. بعد أن تفتحه أرسل لي الرابط وسأتابع ملاحظات المراجعة، تشغيل CI ومراجعة نتائج الاختبار.
