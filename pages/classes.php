<?php
$schoolId = current_user()['school_id'];
$pdo = database();
$sectionQuery = $pdo->prepare('SELECT id, name FROM sections WHERE school_id = ? ORDER BY name');
$sectionQuery->execute([$schoolId]);
$sectionOptions = $sectionQuery->fetchAll();
$classesQuery = $pdo->prepare(
    'SELECT c.id, c.name, c.academic_year, sec.name AS section_name, COUNT(s.id) AS student_count
     FROM classes c LEFT JOIN sections sec ON sec.id = c.section_id
     LEFT JOIN students s ON s.class_id = c.id AND s.status = "active"
     WHERE c.school_id = ? GROUP BY c.id ORDER BY sec.name, c.name'
);
$classesQuery->execute([$schoolId]);
$classes = $classesQuery->fetchAll();
$sectionsQuery = $pdo->prepare('SELECT sec.id, sec.name, COUNT(c.id) AS class_count FROM sections sec LEFT JOIN classes c ON c.section_id = sec.id WHERE sec.school_id = ? GROUP BY sec.id ORDER BY sec.name');
$sectionsQuery->execute([$schoolId]);
$sections = $sectionsQuery->fetchAll();
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('ACADEMIC STRUCTURE')) ?></span><h1><?= e(t('Classes & sections')) ?></h1><p><?= e(t('Organise your school from Nursery through Secondary.')) ?></p></div></section>
<section class="content-grid">
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Add a class')) ?></h2><p><?= e(t('Create a class in one of your school sections.')) ?></p></div><span class="panel-icon">▦</span></div>
        <form id="add-class" method="post" action="index.php?page=classes" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_class">
            <label><?= e(t('Class name')) ?><input name="name" placeholder="<?= e(t('e.g. Primary 1')) ?>" required maxlength="80"></label>
            <label><?= e(t('Section')) ?><select name="section_id"><option value=""><?= e(t('Choose a section')) ?></option><?php foreach ($sectionOptions as $section): ?><option value="<?= (int) $section['id'] ?>"><?= e($section['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Academic year')) ?><input name="academic_year" placeholder="<?= e(t('e.g. 2026–2027')) ?>" maxlength="20"></label>
            <button class="button button-primary" type="submit"><?= e(t('Add class')) ?> <span>→</span></button>
        </form>
    </article>
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Add a section')) ?></h2><p><?= e(t('Sections keep your different school levels together.')) ?></p></div><span class="panel-icon section-icon">◇</span></div>
        <form method="post" action="index.php?page=classes" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_section">
            <label><?= e(t('Section name')) ?><input name="name" placeholder="<?= e(t('e.g. Nursery')) ?>" required maxlength="80"></label>
            <button class="button button-secondary" type="submit"><?= e(t('Create section')) ?> <span>→</span></button>
        </form>
        <div class="section-chips"><?php foreach ($sections as $section): ?><span><?= e(t($section['name'])) ?><small><?= (int) $section['class_count'] ?> <?= e(t('classes')) ?></small></span><?php endforeach; ?></div>
    </article>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Your classes')) ?></h2><p><?= count($classes) ?> <?= e(t(count($classes) === 1 ? 'class' : 'classes')) ?> <?= e(t('set up')) ?></p></div></div>
    <?php if ($classes === []): ?><div class="empty-state compact"><span class="empty-icon">▦</span><p><?= e(t('No classes yet. Add your first class above.')) ?></p></div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th><?= e(t('CLASS')) ?></th><th><?= e(t('Section')) ?></th><th><?= e(t('ACADEMIC YEAR')) ?></th><th><?= e(t('STUDENTS')) ?></th></tr></thead><tbody><?php foreach ($classes as $class): ?><tr><td><strong><?= e($class['name']) ?></strong></td><td><?= e(t($class['section_name'] ?? 'Unassigned')) ?></td><td><?= e($class['academic_year'] ?? '—') ?></td><td><span class="count-badge"><?= (int) $class['student_count'] ?></span></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
