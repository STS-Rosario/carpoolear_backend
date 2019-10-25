@extends('layouts.payment')

@section('title', 'Cómo colaborar - Colaborar - Carpoolear')
@section('body-class', 'body-colaborar body-como-colaborar')

@section('content')
<section>
    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            console.log('DOM fully loaded and parsed');
            setTimeout(function () {
                var message = {
                    mi: 'asdasd'
                };
                var stringifiedMessage = JSON.stringify(message);
                if (window.webkit && window.webkit.messageHandlers && webkit.messageHandlers.cordova_iab) {
                    webkit.messageHandlers.cordova_iab.postMessage(stringifiedMessage);
                    window.close();
                } else {
                    // alert('post messaggin');
                    // window.postMessage(stringifiedMessage);
                    window.location.href = '/app/index.html#/profile/me';
                }
            }, 1500);
        });
    </script>
</section>
@endsection
