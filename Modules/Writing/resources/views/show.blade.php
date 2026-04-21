<x-app-layout>
    <div class="writing-show-header">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Analysis Result</h1>
            <p style="color: var(--text-muted)">Detailed feedback for your IELTS {{ strtoupper($attempt->task_type) }} attempt.</p>
        </div>
        <div style="text-align: right">
            <span style="font-size: 0.875rem; color: var(--text-muted)">Overall Band Score</span>
            <div style="font-size: 3rem; font-weight: 800; color: var(--accent); line-height: 1">
                {{ $attempt->band_score }}
            </div>
        </div>
    </div>

    <!-- Feedback Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 3rem">
        <div class="glass-card" style="border-left: 4px solid var(--primary)">
            <h4 style="margin-bottom: 0.75rem; color: var(--primary)">Grammar & Accuracy</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted)">{{ $attempt->feedback['grammar'] }}</p>
        </div>
        <div class="glass-card" style="border-left: 4px solid #8b5cf6">
            <h4 style="margin-bottom: 0.75rem; color: #8b5cf6">Lexical Resource</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted)">{{ $attempt->feedback['vocabulary'] }}</p>
        </div>
        <div class="glass-card" style="border-left: 4px solid #10b981">
            <h4 style="margin-bottom: 0.75rem; color: #10b981">Coherence & Cohesion</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted)">{{ $attempt->feedback['coherence'] }}</p>
        </div>
        <div class="glass-card" style="border-left: 4px solid #f59e0b">
            <h4 style="margin-bottom: 0.75rem; color: #f59e0b">Task Response</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted)">{{ $attempt->feedback['task_response'] }}</p>
        </div>
    </div>

    <!-- Comparative View -->
    <div class="comparative-grid">
        <div>
            <h3 style="margin-bottom: 1.5rem">Your Original Essay</h3>
            <div class="glass" style="padding: 2rem; border-radius: 12px; min-height: 400px; white-space: pre-wrap; line-height: 1.8; font-size: 0.95rem; color: var(--text-muted)">
                {{ $attempt->essay_content }}
            </div>
        </div>
        <div>
            <h3 style="margin-bottom: 1.5rem">AI Improved Version (Band 8.0+)</h3>
            <div class="glass" style="padding: 2rem; border-radius: 12px; min-height: 400px; white-space: pre-wrap; line-height: 1.8; font-size: 0.95rem; border-color: var(--primary)">
                {{ $attempt->revised_essay }}
            </div>
        </div>
    </div>

    <div style="text-align: center">
        <a href="{{ route('student.writing.index') }}" class="btn btn-outline" style="padding: 0.8rem 2.5rem; border-radius: 50px">
            Try Another Essay
        </a>
    </div>

    <style>
        .writing-show-header {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .comparative-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }
        @media (max-width: 768px) {
            .writing-show-header {
                flex-direction: column;
                align-items: stretch;
            }
            .writing-show-header > div:last-child {
                text-align: left !important;
            }
            .comparative-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-app-layout>
