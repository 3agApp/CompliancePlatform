{{--
    The printed record of one AI document check: what was read, what was
    found, why, and what the factory was to be asked for.

    A record to hand on, so it names the product by every number it has and
    says on the first page that it is advice for a person, not a decision.

    Written for dompdf: tables, inline-friendly styles, points and the
    DejaVu fonts, because nothing else survives the renderer.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('AI document check') }} — {{ $product['name'] }}</title>
    <style>
        @page { margin: 18mm 16mm 20mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.45;
            color: #111;
        }

        h1 { font-size: 16pt; margin: 0 0 2pt; }
        h2 { font-size: 11pt; margin: 16pt 0 6pt; page-break-after: avoid; }
        p { margin: 0 0 6pt; }

        .muted { color: #555; }
        .small { font-size: 8pt; }

        .notice {
            border: 1pt solid #b45309;
            background: #fffbeb;
            padding: 6pt 8pt;
            margin: 10pt 0;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 3pt 6pt 3pt 0; }
        .facts th { width: 34%; font-weight: normal; color: #555; }

        .counts td { padding: 4pt 6pt; border: 0.5pt solid #ccc; text-align: center; }
        .counts .number { font-size: 13pt; font-weight: bold; display: block; }

        .finding {
            border: 0.5pt solid #ccc;
            padding: 6pt 8pt;
            margin-bottom: 6pt;
            page-break-inside: avoid;
        }

        .severity {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            padding: 1pt 4pt;
        }

        .severity-critical { background: #fee2e2; color: #991b1b; }
        .severity-major { background: #fef3c7; color: #92400e; }
        .severity-minor { background: #e0f2fe; color: #075985; }
        .severity-info { background: #f3f4f6; color: #374151; }

        .request {
            background: #f5f5f5;
            padding: 8pt;
            white-space: pre-line;
        }

        .footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            font-size: 7pt;
            color: #777;
        }

        /* dompdf knows the page it is on but not, at this point, how many there will be. */
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td>{{ $product['name'] }} · {{ __('AI document check') }} #{{ $assessment['id'] }}</td>
                <td style="text-align: right;" class="page"></td>
            </tr>
        </table>
    </div>

    <h1>{{ __('AI document check') }}</h1>
    <p class="muted">{{ $product['name'] }}</p>

    <div class="notice">
        {{ __('This report is advice for the person who reviews the product. It is not an approval and does not change the product\'s review status or its seal. A person decides.') }}
    </div>

    <table class="facts">
        <tr><th>{{ __('Product') }}</th><td>{{ $product['name'] }}</td></tr>
        <tr><th>{{ __('Distributor') }}</th><td>{{ $product['distributor'] }}</td></tr>
        @if ($product['supplier'])
            <tr><th>{{ __('Supplier') }}</th><td>{{ $product['supplier'] }}</td></tr>
        @endif
        @if ($product['ean'])
            <tr><th>{{ __('EAN / barcode') }}</th><td>{{ $product['ean'] }}</td></tr>
        @endif
        @if ($product['supplier_article_number'])
            <tr><th>{{ __('Supplier article number') }}</th><td>{{ $product['supplier_article_number'] }}</td></tr>
        @endif
        @if ($product['internal_article_number'])
            <tr><th>{{ __('Internal article number') }}</th><td>{{ $product['internal_article_number'] }}</td></tr>
        @endif
        @if ($product['age_grading'])
            <tr><th>{{ __('Age grading') }}</th><td>{{ $product['age_grading'] }}</td></tr>
        @endif
        <tr><th>{{ __('Review status when printed') }}</th><td>{{ $product['review_status_label'] }}</td></tr>
        <tr>
            <th>{{ __('Check run') }}</th>
            <td>
                {{ $assessment['completed_at'] ? \Illuminate\Support\Carbon::parse($assessment['completed_at'])->isoFormat('LLL') : '' }}
                @if ($assessment['requested_by'])
                    · {{ __('requested by :name', ['name' => $assessment['requested_by']]) }}
                @endif
            </td>
        </tr>
        <tr>
            <th>{{ __('AI model') }}</th>
            <td>{{ $assessment['provider_label'] }} {{ $assessment['model_label'] }} · {{ __('instructions v:version', ['version' => $assessment['prompt_version']]) }}</td>
        </tr>
    </table>

    <h2>{{ __('Conclusion') }}: {{ $assessment['overall_label'] }}</h2>

    @if ($assessment['summary'])
        <p>{{ $assessment['summary'] }}</p>
    @endif

    <table class="counts">
        <tr>
            @foreach ($severityCounts as $severity)
                <td><span class="number">{{ $severity['count'] }}</span>{{ $severity['label'] }}</td>
            @endforeach
        </tr>
    </table>

    @if ($assessment['comparison'])
        <h2>{{ __('Since the check on :date', ['date' => \Illuminate\Support\Carbon::parse($assessment['comparison']['previous_completed_at'])->isoFormat('LL')]) }}</h2>

        <p>
            {{ trans_choice('1 fixed|:count fixed', $assessment['comparison']['resolved_count']) }}
            · {{ trans_choice('1 new|:count new', $assessment['comparison']['new_count']) }}
            · {{ trans_choice('1 still open|:count still open', $assessment['comparison']['still_open_count']) }}
        </p>

        @if (count($assessment['comparison']['resolved']) > 0)
            <p><strong>{{ __('Fixed since the last check') }}</strong></p>
            <table>
                @foreach ($assessment['comparison']['resolved'] as $resolved)
                    <tr>
                        <td>{{ $resolved['requirement'] }}</td>
                        <td class="muted">{{ __('was :severity', ['severity' => $resolved['severity_label']]) }}</td>
                        <td class="muted">{{ $resolved['document_name'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <p class="muted small">{{ __('The AI matches gaps between checks. A gap it words differently can show as one fixed and one new.') }}</p>
    @endif

    <h2>{{ __('Findings') }}</h2>

    @forelse ($assessment['findings'] as $finding)
        <div class="finding">
            <p>
                <span class="severity severity-{{ $finding['severity'] }}">{{ $finding['severity_label'] }}</span>
                <strong>{{ $finding['requirement'] }}</strong>
                <span class="muted small">· {{ $finding['category_label'] }}</span>
                @if ($finding['change'] === 'new')
                    <span class="small"><strong>· {{ __('New') }}</strong></span>
                @elseif ($finding['change'] === 'still_open')
                    <span class="muted small">· {{ $finding['previous_severity_label'] ? __('Still open, was :severity', ['severity' => $finding['previous_severity_label']]) : __('Still open') }}</span>
                @endif
            </p>
            <p>{{ $finding['rationale'] }}</p>
            @if ($finding['evidence'])
                <p class="muted small"><em>“{{ $finding['evidence'] }}”</em></p>
            @endif
            @if ($finding['document_name'])
                <p class="small">{{ __('Document') }}: {{ $finding['document_name'] }}</p>
            @endif
            @if ($finding['ask_manufacturer'])
                <p><strong>{{ __('Ask for:') }}</strong> {{ $finding['ask_manufacturer'] }}</p>
            @endif
        </div>
    @empty
        <p class="muted">{{ __('The check found nothing to flag.') }}</p>
    @endforelse

    <h2>{{ __('Documents read') }}</h2>

    @if (count($assessment['documents']) > 0)
        <table>
            @foreach ($assessment['documents'] as $document)
                <tr>
                    <td>{{ $document['name'] }}</td>
                    <td class="muted">{{ $document['type_label'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="muted">{{ __('No documents could be read.') }}</p>
    @endif

    @if (count($assessment['skipped_documents']) > 0)
        <h2>{{ __('Not read') }}</h2>
        <table>
            @foreach ($assessment['skipped_documents'] as $document)
                <tr>
                    <td>{{ $document['name'] }}</td>
                    <td class="muted">{{ $document['reason'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($assessment['factory_request'])
        <h2>{{ __('Draft request to the factory') }}</h2>
        <div class="request">{{ $assessment['factory_request'] }}</div>
    @endif

    <p class="muted small" style="margin-top: 16pt;">
        {{ __('Printed :date.', ['date' => $generatedAt->isoFormat('LLL')]) }}
    </p>
</body>
</html>
