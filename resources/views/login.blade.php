<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Вход — Домашний чат</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-16x16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css'])
    </head>
    <body class="antialiased bg-gray-50">
        <div class="flex min-h-screen items-center justify-center px-4">
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Домашний чат</h1>
                    <p class="mt-2 text-sm text-gray-500">Войдите, чтобы продолжить</p>
                </div>

                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="login" class="mb-2 block text-sm font-medium text-gray-700">Email или username</label>
                        <input
                            id="login"
                            name="login"
                            type="text"
                            value="{{ old('login') }}"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-gray-700">Пароль</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            @checked(old('remember'))
                        >
                        Запомнить меня
                    </label>

                    <label class="flex items-start gap-2 text-sm text-gray-600">
                        <input
                            id="privacy-consent"
                            type="checkbox"
                            name="privacy_consent"
                            value="1"
                            required
                            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            @checked(old('privacy_consent'))
                        >
                        <span>
                            Я ознакомлен(а) с
                            <button
                                type="button"
                                id="privacy-policy-open"
                                class="text-blue-600 underline underline-offset-2 hover:text-blue-700"
                            >политикой конфиденциальности и обработки персональных данных</button>
                            и даю согласие на обработку моих персональных данных
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-700"
                    >
                        Войти
                    </button>
                </form>
            </div>
        </div>

        <div
            id="privacy-policy-modal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="privacy-policy-title"
        >
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                    <h2 id="privacy-policy-title" class="text-lg font-semibold text-gray-900">
                        Политика конфиденциальности и обработки персональных данных
                    </h2>
                    <button
                        type="button"
                        id="privacy-policy-close"
                        class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Закрыть"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto px-6 py-5 text-sm leading-relaxed text-gray-700">
                    <p class="mb-4 text-gray-500">Дата публикации: 30 июня 2026 г.</p>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">1. Общие положения</h3>
                        <p class="mb-2">
                            Настоящая политика определяет порядок обработки и защиты персональных данных
                            пользователей сервиса «Домашний чат» (далее — Сервис) в соответствии с
                            Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных»,
                            Федеральным законом от 27.07.2006 № 149-ФЗ «Об информации, информационных
                            технологиях и о защите информации», а также иными нормативными правовыми актами
                            Российской Федерации в области персональных данных.
                        </p>
                        <p>
                            Сервис предназначен <strong>исключительно для внутреннего домашнего использования</strong>
                            уполномоченными членами семьи и близким кругом лиц. Сервис не является
                            публичной платформой и не предназначен для массового или коммерческого
                            использования.
                        </p>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">2. Отсутствие самостоятельной регистрации</h3>
                        <p class="mb-2">
                            На Сервисе <strong>не предусмотрена самостоятельная регистрация пользователей</strong>.
                            Учётные записи (логин и пароль) создаются и выдаются <strong>администратором Сервиса</strong>
                            вручную исключительно лицам, имеющим право доступа к внутреннему домашнему чату.
                        </p>
                        <p>
                            Передавая полученные учётные данные третьим лицам, пользователь нарушает
                            условия использования Сервиса и может быть лишён доступа администратором.
                        </p>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">3. Оператор персональных данных</h3>
                        <p>
                            Оператором персональных данных является владелец и администратор Сервиса
                            «Домашний чат» — физическое лицо, развернувшее и обслуживающее Сервис
                            на домашнем сервере. По вопросам обработки персональных данных обращайтесь
                            к администратору Сервиса через доступные вам каналы связи (личное общение,
                            семейный чат вне Сервиса).
                        </p>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">4. Категории обрабатываемых персональных данных</h3>
                        <p class="mb-2">В рамках Сервиса могут обрабатываться следующие данные:</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>имя пользователя (username) и адрес электронной почты (при наличии);</li>
                            <li>хэш пароля (пароль хранится в зашифрованном виде, в открытом виде не сохраняется);</li>
                            <li>текстовые сообщения, отправленные в чате;</li>
                            <li>прикреплённые файлы и изображения, загруженные пользователем;</li>
                            <li>технические данные: IP-адрес, данные сессии, cookies, время входа и активности;</li>
                            <li>сведения о статусе «в сети» / «не в сети» во время использования Сервиса.</li>
                        </ul>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">5. Цели обработки персональных данных</h3>
                        <p class="mb-2">Персональные данные обрабатываются в следующих целях:</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>идентификация и аутентификация пользователя при входе в Сервис;</li>
                            <li>обеспечение функционирования чата и обмена сообщениями между авторизованными пользователями;</li>
                            <li>хранение истории переписки и вложений для участников домашнего чата;</li>
                            <li>обеспечение безопасности Сервиса и предотвращение несанкционированного доступа;</li>
                            <li>техническое обслуживание и устранение сбоев в работе Сервиса.</li>
                        </ul>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">6. Правовые основания обработки</h3>
                        <p class="mb-2">Обработка персональных данных осуществляется на основании:</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>согласия субъекта персональных данных (ст. 9 152-ФЗ), выражаемого при входе в Сервис;</li>
                            <li>необходимости исполнения соглашения об использовании Сервиса между пользователем и администратором;</li>
                            <li>законных интересов оператора по обеспечению работоспособности и безопасности Сервиса.</li>
                        </ul>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">7. Порядок и условия обработки</h3>
                        <p class="mb-2">
                            Обработка персональных данных осуществляется с использованием средств автоматизации
                            и (или) без них. Данные хранятся на сервере, физически расположенном
                            в инфраструктуре владельца Сервиса (домашний сервер).
                        </p>
                        <p class="mb-2">
                            Персональные данные не передаются третьим лицам, за исключением случаев,
                            прямо предусмотренных законодательством Российской Федерации (по запросу
                            уполномоченных государственных органов).
                        </p>
                        <p>
                            Трансграничная передача персональных данных не осуществляется, если иное
                            не будет явно согласовано с субъектом персональных данных.
                        </p>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">8. Сроки хранения</h3>
                        <p>
                            Персональные данные хранятся в течение всего срока использования Сервиса
                            пользователем, а также до момента удаления учётной записи администратором
                            или до отзыва согласия субъектом персональных данных — в зависимости от того,
                            что наступит раньше, если иное не требуется законодательством РФ.
                            Сообщения и вложения могут храниться до их удаления пользователем или администратором.
                        </p>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">9. Меры по защите персональных данных</h3>
                        <p class="mb-2">
                            Оператор принимает необходимые правовые, организационные и технические меры
                            для защиты персональных данных от неправомерного или случайного доступа,
                            уничтожения, изменения, блокирования, копирования, распространения, а также
                            от иных неправомерных действий (ст. 19 152-ФЗ), в том числе:
                        </p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>хранение паролей в хэшированном виде;</li>
                            <li>использование защищённого соединения (HTTPS) при доступе к Сервису;</li>
                            <li>ограничение доступа к серверу и базе данных;</li>
                            <li>регулярное обновление программного обеспечения Сервиса.</li>
                        </ul>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">10. Права субъекта персональных данных</h3>
                        <p class="mb-2">
                            В соответствии со ст. 14–16, 21 152-ФЗ вы имеете право:
                        </p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>получать информацию об обработке ваших персональных данных;</li>
                            <li>требовать уточнения, блокирования или уничтожения персональных данных, если они неполные, устаревшие, неточные или обрабатываются незаконно;</li>
                            <li>отозвать согласие на обработку персональных данных, направив соответствующее обращение администратору Сервиса;</li>
                            <li>обжаловать действия или бездействие оператора в уполномоченный орган по защите прав субъектов персональных данных (Роскомнадзор) или в судебном порядке.</li>
                        </ul>
                        <p class="mt-2">
                            Для реализации своих прав обратитесь к администратору Сервиса. При отзыве
                            согласия доступ к Сервису может быть прекращён, поскольку обработка данных
                            необходима для его функционирования.
                        </p>
                    </section>

                    <section class="mb-5">
                        <h3 class="mb-2 font-semibold text-gray-900">11. Файлы cookie и сессии</h3>
                        <p>
                            Сервис использует файлы cookie и данные сессии для поддержания авторизации
                            пользователя, обеспечения безопасности и корректной работы функций чата.
                            Отключение cookie в браузере делает использование Сервиса невозможным.
                        </p>
                    </section>

                    <section>
                        <h3 class="mb-2 font-semibold text-gray-900">12. Изменение политики</h3>
                        <p>
                            Администратор вправе вносить изменения в настоящую политику. Актуальная версия
                            всегда доступна при входе в Сервис. Продолжение использования Сервиса после
                            публикации изменений означает согласие с обновлённой политикой.
                        </p>
                    </section>
                </div>

                <div class="border-t border-gray-200 px-6 py-4">
                    <button
                        type="button"
                        id="privacy-policy-close-bottom"
                        class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-700"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const modal = document.getElementById('privacy-policy-modal');
                const openBtn = document.getElementById('privacy-policy-open');
                const closeBtn = document.getElementById('privacy-policy-close');
                const closeBottomBtn = document.getElementById('privacy-policy-close-bottom');

                function openModal() {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                }

                function closeModal() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = '';
                }

                openBtn.addEventListener('click', openModal);
                closeBtn.addEventListener('click', closeModal);
                closeBottomBtn.addEventListener('click', closeModal);

                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            })();
        </script>
    </body>
</html>
