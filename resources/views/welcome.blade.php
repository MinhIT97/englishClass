<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('welcome.title') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #030712;
            color: #f3f4f6;
        }
        h1, h2, h3, .font-outfit {
            font-family: 'Outfit', sans-serif;
        }
        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .text-gradient {
            background: linear-gradient(135deg, #818cf8 0%, #e11d48 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .bg-gradient-premium {
            background: linear-gradient(135deg, #4f46e5 0%, #e11d48 100%);
        }
        .hero-glow {
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100vw;
            max-width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.15) 0%, rgba(225, 29, 72, 0) 70%);
            z-index: -1;
            filter: blur(60px);
            pointer-events: none;
        }
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        .floating { animation: floating 4s ease-in-out infinite; }
        
        .lang-link {
            transition: all 0.3s ease;
        }
        .lang-link.active {
            color: #818cf8;
            font-weight: 700;
        }
    </style>
</head>
<body class="antialiased overflow-x-hidden">
    <div class="hero-glow"></div>

    <!-- Navigation -->
    <nav id="navbar" class="fixed top-0 w-full z-50 transition-all duration-300 px-2 sm:px-6 py-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center glass rounded-xl sm:rounded-2xl px-3 sm:px-6 py-2 sm:py-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 sm:w-10 sm:h-10 shrink-0 bg-gradient-premium rounded-xl flex items-center justify-center font-outfit font-bold text-lg sm:text-xl shadow-lg shadow-indigo-500/20">
                    I
                </div>
                <span class="font-outfit font-bold text-base sm:text-xl tracking-tight hidden sm:block">IELTS <span class="text-indigo-400">AI</span></span>
            </div>

            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-300">
                <a href="#features" class="hover:text-white transition-colors">{{ __('welcome.nav.features') }}</a>
                <a href="#statistics" class="hover:text-white transition-colors">{{ __('welcome.nav.statistics') }}</a>
                <a href="#community" class="hover:text-white transition-colors">{{ __('welcome.nav.community') }}</a>
            </div>

            <div class="flex items-center gap-2 sm:gap-6">
                <!-- Language Switcher -->
                <div class="flex items-center gap-1 sm:gap-2 text-[10px] sm:text-xs font-bold font-outfit tracking-widest border-r border-white/10 pr-2 sm:pr-6 shrink-0">
                    <a href="{{ route('set.locale', 'en') }}" class="lang-link {{ app()->getLocale() == 'en' ? 'active' : 'text-gray-500 hover:text-gray-300' }}">EN</a>
                    <span class="text-white/10">|</span>
                    <a href="{{ route('set.locale', 'vi') }}" class="lang-link {{ app()->getLocale() == 'vi' ? 'active' : 'text-gray-500 hover:text-gray-300' }}">VI</a>
                </div>

                <div class="flex items-center gap-1 sm:gap-3">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ auth()->user()->role === 'admin' ? route('admin.dashboard') : route('student.dashboard') }}" 
                               class="px-3 sm:px-5 py-2 rounded-xl bg-gradient-premium text-white font-semibold text-xs sm:text-sm shadow-lg shadow-indigo-500/20 hover:scale-105 transition-transform whitespace-nowrap shrink-0">
                                {{ __('welcome.nav.dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="px-2 sm:px-4 py-2 text-xs sm:text-sm font-medium hover:text-white transition-colors whitespace-nowrap shrink-0">{{ __('welcome.nav.login') }}</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" 
                                   class="px-3 sm:px-5 py-2 rounded-xl bg-gradient-premium text-white font-semibold text-[10px] sm:text-sm shadow-lg shadow-indigo-500/20 hover:scale-105 transition-transform whitespace-nowrap shrink-0">
                                    {{ __('welcome.nav.register') }}
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-28 sm:pt-32 md:pt-32 lg:pt-28 pb-20 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div class="relative z-10 text-center md:text-left space-y-6 lg:space-y-8">
                <div class="hidden sm:inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-semibold uppercase tracking-wider">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                    </span>
                    {{ __('welcome.hero.badge') }}
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-7xl font-outfit font-extrabold leading-tight">
                    {{ __('welcome.hero.title') }} <br>
                    <span class="text-gradient">{{ __('welcome.hero.title_accent') }}</span>
                </h1>
                <p class="text-gray-400 text-base md:text-lg max-w-xl mx-auto md:mx-0 leading-relaxed">
                    {{ __('welcome.hero.subtitle') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
                    <a href="{{ route('register') }}" class="px-8 py-4 rounded-2xl bg-gradient-premium text-white font-bold text-lg shadow-xl shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:-translate-y-1 transition-all">
                        {{ __('welcome.hero.cta_start') }}
                    </a>
                    <a href="#features" class="px-8 py-4 rounded-2xl glass hover:bg-white/5 font-semibold text-lg transition-all">
                        {{ __('welcome.hero.cta_explore') }}
                    </a>
                </div>
                <div class="flex items-center justify-center md:justify-start gap-4 pt-4">
                    <div class="flex -space-x-3">
                        @for ($i = 1; $i <= 4; $i++)
                            <div class="w-10 h-10 rounded-full border-2 border-[#030712] bg-gray-800 flex items-center justify-center text-xs font-bold font-outfit">S{{ $i }}</div>
                        @endfor
                        <div class="w-10 h-10 rounded-full border-2 border-[#030712] bg-indigo-600 flex items-center justify-center text-[10px] font-bold">+50k</div>
                    </div>
                    <span class="text-sm text-gray-400 font-medium">{{ __('welcome.hero.stats') }}</span>
                </div>
            </div>
            <div class="relative flex justify-center mt-8 md:mt-0">
                <div class="relative z-10 floating">
                    <img src="{{ asset('images/hero_student.png') }}" alt="IELTS AI Student" class="w-full max-w-[550px] rounded-[2.5rem] shadow-2xl">
                    <div class="hidden sm:block absolute -bottom-6 -left-6 bg-[#030712]/80 backdrop-blur-xl border border-white/10 p-4 rounded-2xl shadow-2xl">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 text-2xl">✓</div>
                            <div>
                                <div class="text-xs text-gray-400 uppercase font-bold tracking-widest">{{ __('welcome.hero.target_band') }}</div>
                                <div class="text-xl font-outfit font-bold">{{ __('welcome.hero.over_8') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Animated Background Elements -->
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-indigo-500/10 blur-[100px] -z-10 rounded-full"></div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 px-6 relative overflow-hidden">
        <div class="max-w-7xl mx-auto space-y-16">
            <div class="text-center space-y-4 max-w-2xl mx-auto">
                <h2 class="text-3xl lg:text-4xl font-outfit font-bold">{{ __('welcome.features.title') }} <span class="text-indigo-400">{{ __('welcome.features.title_accent') }}</span>?</h2>
                <p class="text-gray-400">{{ __('welcome.features.subtitle') }}</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Speaking -->
                <div class="glass p-8 rounded-[2rem] hover:bg-white/5 transition-all group">
                    <div class="w-14 h-14 bg-indigo-500/10 rounded-2xl flex items-center justify-center text-indigo-400 text-3xl mb-6 group-hover:bg-gradient-premium group-hover:text-white transition-all">🗣️</div>
                    <h3 class="text-xl font-outfit font-bold mb-3">{{ __('welcome.features.speaking.title') }}</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">{{ __('welcome.features.speaking.desc') }}</p>
                </div>
                <!-- Writing -->
                <div class="glass p-8 rounded-[2rem] hover:bg-white/5 transition-all group">
                    <div class="w-14 h-14 bg-rose-500/10 rounded-2xl flex items-center justify-center text-rose-400 text-3xl mb-6 group-hover:bg-gradient-premium group-hover:text-white transition-all">✍️</div>
                    <h3 class="text-xl font-outfit font-bold mb-3">{{ __('welcome.features.writing.title') }}</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">{{ __('welcome.features.writing.desc') }}</p>
                </div>
                <!-- Mock Tests -->
                <div class="glass p-8 rounded-[2rem] hover:bg-white/5 transition-all group">
                    <div class="w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center text-emerald-400 text-3xl mb-6 group-hover:bg-gradient-premium group-hover:text-white transition-all">🏆</div>
                    <h3 class="text-xl font-outfit font-bold mb-3">{{ __('welcome.features.mock.title') }}</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">{{ __('welcome.features.mock.desc') }}</p>
                </div>
                <!-- Adaptive -->
                <div class="glass p-8 rounded-[2rem] hover:bg-white/5 transition-all group">
                    <div class="w-14 h-14 bg-amber-500/10 rounded-2xl flex items-center justify-center text-amber-400 text-3xl mb-6 group-hover:bg-gradient-premium group-hover:text-white transition-all">⚡</div>
                    <h3 class="text-xl font-outfit font-bold mb-3">{{ __('welcome.features.adaptive.title') }}</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">{{ __('welcome.features.adaptive.desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section id="statistics" class="py-20 px-6">
        <div class="max-w-7xl mx-auto glass rounded-[2rem] sm:rounded-[3rem] p-6 sm:p-12 lg:p-20 grid md:grid-cols-3 gap-8 text-center border-indigo-500/10">
            <div class="space-y-2">
                <div class="text-5xl lg:text-6xl font-outfit font-black text-indigo-400">95%</div>
                <div class="text-lg font-semibold uppercase tracking-widest text-gray-500">{{ __('welcome.stats.success_rate') }}</div>
                <p class="text-sm text-gray-400">{{ __('welcome.stats.success_desc') }}</p>
            </div>
            <div class="space-y-2 border-y md:border-y-0 md:border-x border-white/5 py-8 md:py-0">
                <div class="text-5xl lg:text-6xl font-outfit font-black text-rose-400">7.5+</div>
                <div class="text-lg font-semibold uppercase tracking-widest text-gray-500">{{ __('welcome.stats.average_band') }}</div>
                <p class="text-sm text-gray-400">{{ __('welcome.stats.average_desc') }}</p>
            </div>
            <div class="space-y-2">
                <div class="text-5xl lg:text-6xl font-outfit font-black text-emerald-400">2M+</div>
                <div class="text-lg font-semibold uppercase tracking-widest text-gray-500">{{ __('welcome.stats.essays_graded') }}</div>
                <p class="text-sm text-gray-400">{{ __('welcome.stats.essays_desc') }}</p>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="community" class="py-24 px-6 bg-[#030712] relative">
        <div class="max-w-7xl mx-auto space-y-12">
            <div class="flex flex-col md:flex-row justify-between items-end gap-6">
                <div class="space-y-4">
                    <h2 class="text-3xl lg:text-4xl font-outfit font-bold">{{ __('welcome.testimonials.title') }} <br> <span class="text-indigo-400">{{ __('welcome.testimonials.title_accent') }}</span></h2>
                </div>
                <div class="text-indigo-400 font-semibold flex items-center gap-2 cursor-pointer hover:underline">
                    {{ __('welcome.testimonials.link') }} <span>→</span>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $testimonials = [
                        ['name' => 'Minh Nguyen', 'band' => '8.5', 'text' => app()->getLocale() == 'vi' ? "Cố vấn Speaking AI là một cuộc cách mạng. Nó phát hiện ra các lỗi phát âm mà tôi thậm chí không biết là mình mắc phải." : "The AI Speaking coach is a game changer. It caught my pronunciation errors that I didn't even know existed."],
                        ['name' => 'Sarah Johnson', 'band' => '8.0', 'text' => app()->getLocale() == 'vi' ? "Tôi đã tăng từ 6.5 lên 8.0 Writing chỉ trong 3 tuần nhờ hệ thống phản hồi tức thì. Thật sự không thể tin được." : "I went from a 6.5 to an 8.0 in Writing in just 3 weeks with the instant feedback system. Absolutely incredible."],
                        ['name' => 'Hiroshi Sato', 'band' => '7.5', 'text' => app()->getLocale() == 'vi' ? "Các bài thi thử giống hệt đề thi thật. Nó giúp tôi vượt qua áp lực phòng thi một cách đáng kể." : "The mock tests are identical to the real exam. It helped me overcome the test anxiety significantly."]
                    ];
                @endphp

                @foreach($testimonials as $t)
                <div class="glass p-8 rounded-[2.5rem] space-y-6 relative border-indigo-500/10">
                    <div class="flex justify-between items-center">
                        <div class="w-12 h-12 bg-gray-800 rounded-full"></div>
                        <div class="px-3 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 font-bold font-outfit">Band {{ $t['band'] }}</div>
                    </div>
                    <p class="text-gray-300 italic">"{{ $t['text'] }}"</p>
                    <div class="font-outfit font-bold text-gray-100">{{ $t['name'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-20 px-6">
        <div class="max-w-5xl mx-auto bg-gradient-premium rounded-[2rem] sm:rounded-[3rem] p-8 sm:p-12 lg:p-20 text-center space-y-8 shadow-2xl shadow-rose-500/10">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-outfit font-extrabold text-white">{{ __('welcome.cta_final.title') }}</h2>
            <p class="text-white/80 text-lg max-w-2xl mx-auto">{{ __('welcome.cta_final.subtitle') }}</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="px-10 py-5 bg-white text-rose-600 font-bold text-xl rounded-2xl hover:bg-gray-100 transition-all">
                    {{ __('welcome.cta_final.register') }}
                </a>
                <a href="{{ route('login') }}" class="px-10 py-5 bg-black/20 text-white font-bold text-xl rounded-2xl border border-white/20 hover:bg-black/30 transition-all">
                    {{ __('welcome.cta_final.login') }}
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 px-6 border-t border-white/5">
        <div class="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-premium rounded-lg flex items-center justify-center font-outfit font-bold text-lg">I</div>
                    <span class="font-outfit font-bold text-lg tracking-tight">IELTS <span class="text-indigo-400">AI</span></span>
                </div>
                <p class="text-gray-500 text-sm">{{ __('welcome.footer.mission') }}</p>
            </div>
            <div class="space-y-4">
                <h4 class="font-bold font-outfit">{{ __('welcome.footer.product') }}</h4>
                <ul class="text-gray-500 text-sm space-y-2">
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.features.speaking.title') }}</a></li>
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.features.writing.title') }}</a></li>
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.features.mock.title') }}</a></li>
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.features.adaptive.title') }}</a></li>
                </ul>
            </div>
            <div class="space-y-4">
                <h4 class="font-bold font-outfit">{{ __('welcome.footer.company') }}</h4>
                <ul class="text-gray-500 text-sm space-y-2">
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.footer.about') }}</a></li>
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.footer.success') }}</a></li>
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.footer.privacy') }}</a></li>
                    <li><a href="#" class="hover:text-indigo-400">{{ __('welcome.footer.terms') }}</a></li>
                </ul>
            </div>
            <div class="space-y-4">
                <h4 class="font-bold font-outfit">{{ __('welcome.footer.connect') }}</h4>
                <div class="flex gap-4">
                    <a href="#" class="w-10 h-10 glass rounded-lg flex items-center justify-center hover:bg-indigo-500/20 transition-all">𝕏</a>
                    <a href="#" class="w-10 h-10 glass rounded-lg flex items-center justify-center hover:bg-indigo-500/20 transition-all">FB</a>
                    <a href="#" class="w-10 h-10 glass rounded-lg flex items-center justify-center hover:bg-indigo-500/20 transition-all">IG</a>
                </div>
            </div>
        </div>
        <div class="max-w-7xl mx-auto pt-12 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-gray-500 text-xs text-center">&copy; {{ date('Y') }} IELTS Mastery Platform. {{ __('welcome.footer.rights') }}</p>
            <div class="flex gap-6 text-xs text-gray-500 font-medium">
                <span>Crafted with <span class="text-rose-500">♥</span> by Minh Nguyen</span>
            </div>
        </div>
    </footer>

    <script>
        // Navbar glass effect on scroll
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                navbar.classList.add('bg-[#030712]/50', 'backdrop-blur-xl', 'py-2');
                navbar.classList.remove('py-4');
            } else {
                navbar.classList.remove('bg-[#030712]/50', 'backdrop-blur-xl', 'py-2');
                navbar.classList.add('py-4');
            }
        });
    </script>
</body>
</html>
