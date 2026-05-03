<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">{{ __('ui.mock_test_center') }}</h1>
        <p style="color: var(--text-muted)">{{ __('ui.mock_test_desc') }}</p>
    </div>

    <!-- Stats Overview -->
    <div class="mocktest-stats-grid">
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem">
            <div style="font-size: 2rem">🏁</div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">{{ __('ui.tests_taken') }}</div>
                <div style="font-size: 1.25rem; font-weight: 700;">0</div>
            </div>
        </div>
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem">
            <div style="font-size: 2rem">⏱️</div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">{{ __('ui.avg_score') }}</div>
                <div style="font-size: 1.25rem; font-weight: 700;">N/A</div>
            </div>
        </div>
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem">
            <div style="font-size: 2rem">🎯</div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">{{ __('ui.next_milestone') }}</div>
                <div style="font-size: 1.25rem; font-weight: 700;">Band {{ auth()->user()->target_band ?? '7.5' }}</div>
            </div>
        </div>
    </div>

    <h2 style="margin-bottom: 1.5rem">{{ __('ui.available_full_tests') }}</h2>
    <div class="mocktest-full-tests-grid">
        <!-- Test Case 1 -->
        <div class="card" style="position: relative; overflow: hidden; padding: 2rem; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="position: absolute; top: 0; right: 0; background: var(--primary); color: white; padding: 0.5rem 1.5rem; font-size: 0.7rem; font-weight: 700; border-bottom-left-radius: 12px;">ACADEMIC</div>
            <h3 style="margin-bottom: 0.5rem; margin-top: 0.5rem">{{ __('ui.full_simulation') }} #01</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 2rem">{{ __('ui.test_include') }}</p>
            
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem">
                <div style="display: flex; gap: 0.5rem">
                    <span title="Listening" style="opacity: 0.5">🎧</span>
                    <span title="Reading" style="opacity: 0.5">📖</span>
                    <span title="Writing" style="opacity: 0.5">✍️</span>
                    <span title="Speaking" style="opacity: 0.5">🗣️</span>
                </div>
                <a href="{{ route('student.test.start') }}" class="btn btn-primary mocktest-start-btn">{{ __('ui.start_full_test') }}</a>
            </div>
        </div>

        <!-- Test Case 2 -->
        <div class="card" style="position: relative; overflow: hidden; padding: 2rem; opacity: 0.7; border-style: dashed">
            <div style="position: absolute; top: 0; right: 0; background: var(--text-muted); color: white; padding: 0.5rem 1.5rem; font-size: 0.7rem; font-weight: 700; border-bottom-left-radius: 12px;">GENERAL</div>
            <h3 style="margin-bottom: 0.5rem; margin-top: 0.5rem">{{ __('ui.full_simulation') }} #02</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 2rem">{{ __('ui.coming_soon') }}</p>
            
            <div style="display: flex; align-items: center; justify-content: flex-end">
                <button class="btn btn-outline mocktest-start-btn" disabled style="cursor: not-allowed">{{ __('ui.locked') }}</button>
            </div>
        </div>
    </div>

    <h2 style="margin-bottom: 1.5rem">{{ __('ui.skill_mock_tests') }}</h2>
    <div class="mocktest-skills-grid">
        <div class="card" style="text-align: center; padding: 1.5rem; cursor: pointer" onclick="window.location.href='{{ route('student.practice.drill', 'listening') }}'" onmouseover="this.querySelector('.go').style.opacity='1'" onmouseout="this.querySelector('.go').style.opacity='0'">
            <div style="font-size: 2.5rem; margin-bottom: 1rem">🎧</div>
            <div style="font-weight: 600; margin-bottom: 0.5rem">{{ __('ui.listening') }}</div>
            <div class="go" style="color: var(--primary); font-size: 0.75rem; font-weight: 700; opacity: 0; transition: opacity 0.2s">{{ __('ui.enter') }}</div>
        </div>
        <div class="card" style="text-align: center; padding: 1.5rem; cursor: pointer" onclick="window.location.href='{{ route('student.practice.drill', 'reading') }}'" onmouseover="this.querySelector('.go').style.opacity='1'" onmouseout="this.querySelector('.go').style.opacity='0'">
            <div style="font-size: 2.5rem; margin-bottom: 1rem">📖</div>
            <div style="font-weight: 600; margin-bottom: 0.5rem">{{ __('ui.reading') }}</div>
            <div class="go" style="color: var(--primary); font-size: 0.75rem; font-weight: 700; opacity: 0; transition: opacity 0.2s">{{ __('ui.enter') }}</div>
        </div>
        <div class="card" style="text-align: center; padding: 1.5rem; cursor: pointer" onclick="window.location.href='{{ route('student.writing.index') }}'" onmouseover="this.querySelector('.go').style.opacity='1'" onmouseout="this.querySelector('.go').style.opacity='0'">
            <div style="font-size: 2.5rem; margin-bottom: 1rem">✍️</div>
            <div style="font-weight: 600; margin-bottom: 0.5rem">{{ __('ui.writing') }}</div>
            <div class="go" style="color: var(--primary); font-size: 0.75rem; font-weight: 700; opacity: 0; transition: opacity 0.2s">{{ __('ui.enter') }}</div>
        </div>
        <div class="card" style="text-align: center; padding: 1.5rem; cursor: pointer" onclick="window.location.href='{{ route('student.speaking.index') }}'" onmouseover="this.querySelector('.go').style.opacity='1'" onmouseout="this.querySelector('.go').style.opacity='0'">
            <div style="font-size: 2.5rem; margin-bottom: 1rem">🗣️</div>
            <div style="font-weight: 600; margin-bottom: 0.5rem">{{ __('ui.speaking') }}</div>
            <div class="go" style="color: var(--primary); font-size: 0.75rem; font-weight: 700; opacity: 0; transition: opacity 0.2s">{{ __('ui.enter') }}</div>
        </div>
    </div>
    
    <style>
        .mocktest-stats-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 3rem;
        }
        .mocktest-full-tests-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 2rem; 
            margin-bottom: 4rem;
        }
        .mocktest-skills-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 1rem;
        }
        .mocktest-start-btn {
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
        }
        @media (max-width: 640px) {
            .mocktest-start-btn {
                width: 100%;
            }
            .mocktest-skills-grid .go {
                opacity: 1 !important; /* Always show enter text on mobile */
            }
        }
    </style>
</x-app-layout>
