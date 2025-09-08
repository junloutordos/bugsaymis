// resources/js/firebase.js
import { initializeApp } from "firebase/app";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "firebase/auth";

const firebaseConfig = {
  apiKey: "AIzaSyBAAczTfTw5sQ3OCbs6WNbB_VPAghmZRws",
  authDomain: "bugsaymis.firebaseapp.com",
  projectId: "bugsaymis",
  storageBucket: "bugsaymis.firebasestorage.app",
  messagingSenderId: "54745889381",
  appId: "1:54745889381:web:954dbdf341c826013aa48c",
  measurementId: "G-QV9JZ2BYLJ"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const provider = new GoogleAuthProvider();

// Force PSHS domain
provider.setCustomParameters({
  hd: "crc.pshs.edu.ph"
});

export { auth, provider, signInWithPopup };
