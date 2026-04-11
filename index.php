<?php
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';
?>

<section class="hero">
    <div class="hero-content">
        <div class="hero-text">
            <span class="badge">Подготовка к ЕГЭ 2026</span>
            <h1>Твоя база для победы на <span>экзаменах</span></h1>
            <p>Актуальные задачи по Математике, Физике, Информатике и Русскому языку. Всё в одном месте и без лишней воды.</p>
            <div class="hero-actions">
                <a href="#main-nav" class="btn-primary">Начать решать</a>
                <a href="#" class="btn-outline">Справочник формул</a>
            </div>
        </div>
        <div class="hero-visual">
            <div class="abstract-orb"></div>
        </div>
    </div>
</section>

<main class="container" id="main-nav">
    <div class="section-title">
        <h2>Направления работы</h2>
    </div>
    <div class="subjects-grid">
        <div class="subject-card-large">
            <div class="card-info">
                <span class="card-tag">Тренировка</span>
                <h3>Каталог задач</h3>
                <p>Задания разбиты по темам и номерам ЕГЭ. Учись решать конкретные типы задач с подсказками.</p>
            </div>
            <div class="card-footer">
                <a href="catalog.php" class="go-link">К задачам →</a>
            </div>
        </div>

        <div class="subject-card-large highlight">
            <div class="card-info">
                <span class="card-tag highlight-tag">Экзамен</span>
                <h3>Варианты по предметам</h3>
                <p>Полноценные тренировочные варианты. Проверь свои силы в условиях, максимально близких к реальным.</p>
            </div>
            <div class="card-footer">
                <a href="variants.php" class="go-link">Выбрать предмет →</a>
            </div>
        </div>

        <div class="subject-card-large">
            <div class="card-info">
                <span class="card-tag">База</span>
                <h3>Полезные материалы</h3>
                <p>Шпаргалки, методички и краткие конспекты по всем темам, которые встретятся в КИМах.</p>
            </div>
            <div class="card-footer">
                <a href="theory.html" class="go-link">Открыть теорию →</a>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>