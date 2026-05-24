<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zendo — Retreat Centers</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        h1 { color: #4338ca; }
        .center { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        .center h2 { margin: 0 0 0.5rem 0; }
        .features { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .badge { background: #f3f4f6; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.inactive { background: #f3f4f6; color: #9ca3af; }
    </style>
</head>
<body>
    <h1>Zendo</h1>
    <p>Find your retreat.</p>

    @foreach($centers as $center)
        <div class="center">
            <h2>{{ $center->name }}</h2>
            <p>{{ $center->description }}</p>
            <div class="features">
                @foreach(['meals', 'lodging', 'memberships'] as $feature)
                    @if(isset($center->features[$feature]) && $center->features[$feature])
                        <span class="badge active">{{ $feature }}</span>
                    @else
                        <span class="badge inactive">{{ $feature }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</body>
</html>
