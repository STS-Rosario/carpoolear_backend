@extends('layouts.master')

@section('title', 'Cómo colaborar - Colaborar - Carpoolear')
@section('body-class', 'body-colaborar body-como-colaborar')

@section('content')
<section>
    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            console.log('DOM fully loaded and parsed');
            setTimeout(function () {
                window.close();
                var message = {
                    mi: 'asdasd'
                };
                var stringifiedMessage = JSON.stringify(message);
                webkit.messageHandlers.cordova_iab.postMessage(stringifiedMessage);
            }, 500);
        });
    </script>
</section>
@endsection
