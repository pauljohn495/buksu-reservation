<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: main/index.php');
    exit();
}

// --- RECAPTCHA VERIFICATION ---
$recaptcha_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['g-recaptcha-response'])) {
    $recaptcha_secret = '6LduuzQrAAAAAH6Ro5C6y2ccNdKHESBY30hkgy2N'; 
    $recaptcha_response = $_POST['g-recaptcha-response'];
    $remoteip = $_SERVER['REMOTE_ADDR'];
    $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}&remoteip={$remoteip}");
    $captcha_success = json_decode($verify);
    if ($captcha_success && $captcha_success->success) {
        header('Location: ' . $_POST['google_auth_url']);
        exit();
    } else {
        $recaptcha_error = 'reCAPTCHA verification failed. Please try again.';
    }
}

require_once 'vendor/autoload.php'; 

$client = new Google_Client();
$client->setClientId('291360629779-blq0a1vat8hvdl29ltqjrp1mjnrtbbfo.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/BUKSU-RESERVATION/google_callback.php');
$client->addScope("email");
$client->addScope("profile");

$authUrl = $client->createAuthUrl();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!--Google Fonts-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>BUKSU Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

  <!--boxicons-->
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
      <!--bootstrap-->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js" integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+" crossorigin="anonymous"></script>
      
    <!--Scroll Reveal effect-->
    <script src="https://unpkg.com/scrollreveal"></script>

    <div class="container">

        <div class="card" style="width: 26rem; background-color: rgba(255, 255, 255, 0.296); backdrop-filter: blur(10px); ">
        <img src="img/logo.png" class="card-img-top" alt="...">
        <div class="card-body" style="color: rgb(255, 255, 255);">
              <h5 class="card-title">Welcome to BUKSU Library</h5>
              <?php if (!empty($recaptcha_error)): ?>
                <div class="alert alert-danger"><?php echo $recaptcha_error; ?></div>
              <?php endif; ?>
              <form method="post" id="loginForm">
                <input type="hidden" name="google_auth_url" value="<?php echo htmlspecialchars($authUrl); ?>">
                <div class="mb-3">
                  <div class="g-recaptcha" data-sitekey="6LduuzQrAAAAAFAqtjnGzYHNr7Qm8_dE5ADb_6mp"></div> 
                </div>
                <button type="submit" class="google-button" style="display: flex; align-items: center; justify-content: center; 
                background-color: #4285F4; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: 500; transition: background-color 0.3s; width: 100%; border: none;">
                  <box-icon name='google' type='logo' color="white" style="margin-right: 10px;"></box-icon>
                  Login or Signup using Google
                </button>
              </form>
            </div>

          </div>

    </div>

    <script>
      const sr = ScrollReveal ({
        distance: '65px',
        duration: 2600,
        delay: 450,
        reset: true
      }); 

      sr.reveal('.card',{delay: 200, origin:'top'});
      // Prevent form submit if reCAPTCHA not checked
      document.getElementById('loginForm').addEventListener('submit', function(e) {
        if (grecaptcha.getResponse() === '') {
          e.preventDefault();
          alert('Please complete the reCAPTCHA.');
        }
      });

    </script>
</body>
</html>