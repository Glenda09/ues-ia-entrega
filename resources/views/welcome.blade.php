<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ env('APP_NAME', 'Gobierno de El Salvador') }}</title>

    <style>
        @font-face {
            font-family: 'Open Sans';
            src: url('/fonts/OpenSans.ttf') format('truetype');
            font-display: swap;
        }

        :root {
            --bg: #0f1833;
            --bg-2: #0b1530;
            --card: #1a2548;
            --text: #f4f7ff;
            --muted: #d8e0fa;
            --accent: #16f59f;
            --accent-2: #21c7ff;
            --warning-bg: #222d4f;
            --shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            background: radial-gradient(130% 130% at 20% 20%, #152149 0%, var(--bg) 45%, var(--bg-2) 100%);
            color: var(--text);
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            font: inherit;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2.4rem;
            padding: 2.5rem 1.5rem 3rem;
        }

        .logos {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3.5rem;
        }

        @media (max-width: 900px) {
            .logos {
                gap: 2rem;
            }

            .logo-goes {
                height: 72px;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <header class="logos">
            <h1>{{ env('APP_NAME', 'Gobierno de El Salvador') }}</h1>
        </header>
    </div>
</body>

</html>
