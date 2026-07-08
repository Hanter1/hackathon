<?php
/**
 * Переводы сущностей CMS (BE / EN) по умолчанию.
 * Русский — в основных таблицах. Переопределение — в cms_entity_i18n или админке редактирования.
 *
 * Ключи:
 * - by_slug: slug записи
 * - by_title: точное русское название (fallback, если slug не задан в файле)
 */
return [
    'course' => [
        'by_slug' => [],
        'by_title' => [
            'Человек общающийся: слушать, говорить, взаимодействовать' => [
                'en' => [
                    'title' => 'The Communicative Person: Listen, Speak, Connect',
                    'category' => 'Soft skills',
                ],
                'be' => [
                    'title' => 'Чалавек, які ўмее общацца: слухаць, гаварыць, узаемадзейнічаць',
                    'category' => 'Мяккія навыкі',
                ],
            ],
            'Деньги под контролем' => [
                'en' => [
                    'title' => 'Money Under Control',
                    'category' => 'Finance',
                ],
                'be' => [
                    'title' => 'Грошы пад кантролем',
                    'category' => 'Фінансы',
                ],
            ],
            'Треугольник Успеха' => [
                'en' => [
                    'title' => 'Triangle of Success',
                    'category' => 'Soft & hard skills',
                ],
                'be' => [
                    'title' => 'Трохкутнік поспеху',
                    'category' => 'Мяккія і твёрдыя навыкі',
                ],
            ],
        ],
    ],
    'blog_post' => [
        'by_slug' => [],
        'by_title' => [
            'Профориентация и работа для молодежи: VIBES | SOS-Детские деревни' => [
                'en' => [
                    'title' => 'Career Guidance and Jobs for Youth: VIBES | SOS Children\'s Villages',
                    'author_name' => 'SOS Children\'s Villages NGO',
                    'excerpt' => 'Career guidance and employment support for young people: programmes, mentoring, and practical steps toward your first job.',
                ],
                'be' => [
                    'title' => 'Прафоріентацыя і работа для моладзі: VIBES | SOS-Дзіцячыя вёскі',
                    'author_name' => 'МГА «SOS-Дзіцячыя вёскі»',
                    'excerpt' => 'Дапамога ў прафесійным выбары і праца для маладых людзей: праграмы, настаўніцтва і практычныя крокі да першай працы.',
                ],
            ],
            'Навыки будущего: что нужно знать и уметь в XXI веке' => [
                'en' => [
                    'title' => 'Future Skills: What to Know and Be Able to Do in the 21st Century',
                    'author_name' => 'News',
                    'excerpt' => 'Digital literacy, critical thinking, communication, and adaptability — the skills that help you stay relevant in a changing world.',
                ],
                'be' => [
                    'title' => 'Навыкі будучыні: што трэба ведаць і ўмець у XXI стагоддзі',
                    'author_name' => 'Навіна',
                    'excerpt' => 'Лічбавая граматнасць, крытычнае мысленне, камунікацыя і адаптыўнасць — навыкі, якія дапамогуць заставацца ў трэндзе ў змяняльным свеце.',
                ],
            ],
        ],
    ],
    'teacher' => [
        'by_slug' => [],
        'by_title' => [],
    ],
    'event' => [
        'by_slug' => [],
        'by_title' => [],
    ],
];
