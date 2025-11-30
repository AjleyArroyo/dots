<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FCM - Firebase Push Notification</title>
</head>

<body>
    <h1>Firebase Push Notification</h1>

    <script type="module">
        // Import Firebase SDK functions
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getMessaging, getToken } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js";

        // Firebase Configuration (replace with your own)
        const firebaseConfig = {
            apiKey: "AIzaSyB_EKVCPO1lCx4hPuyVjVf3xACL48MI26w",
            authDomain: "gabineteinteligente-71609.firebaseapp.com",
            projectId: "gabineteinteligente-71609",
            storageBucket: "gabineteinteligente-71609.firebasestorage.app",
            messagingSenderId: "404798049461",
            appId: "1:404798049461:web:d86122551ff84bdd42c2fc",
            measurementId: "G-ZXWQTWHL6C"
        };

        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const messaging = getMessaging(app);

        // Register the Service Worker and request permission for notifications
        navigator.serviceWorker.register('sw.js').then(function (registration) {
            console.log('Service Worker registered with scope: ', registration.scope);

            // Get Firebase Cloud Messaging token
            getToken(messaging, {
                serviceWorkerRegistration: registration,
                vapidKey: 'BErfw-EqM-Ut72kl18Lb-pYCn_jiVCJhuvLonB3COvjblo2c5laFCDvZ-9_lb94FSWoshlHzgiFADIjnnLmnXAY'
            }).then((currentToken) => {
                if (currentToken) {
                    console.log("FCM Token:", currentToken);
                    // Send the token to your server for further processing (optional)
                    // sendTokenToServer(currentToken);
                } else {
                    console.log("No registration token available. Request permission to generate one.");
                }
            }).catch((err) => {
                console.log('An error occurred while retrieving token: ', err);
            });
        }).catch(function (error) {
            console.error('Service Worker registration failed: ', error);
        });
    </script>
</body>

</html>


