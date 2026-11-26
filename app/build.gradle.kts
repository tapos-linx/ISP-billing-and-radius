import com.google.gms.googleservices.GoogleServicesPlugin.MissingGoogleServicesStrategy

plugins {
  alias(libs.plugins.android.application)
  alias(libs.plugins.kotlin.compose)
  alias(libs.plugins.google.devtools.ksp)
  alias(libs.plugins.secrets)
  alias(libs.plugins.google.services)
}

android {
  namespace = "com.example"
  compileSdk { version = release(36) { minorApiLevel = 1 } }

  defaultConfig {
    applicationId = "com.aistudio.ispbilling.txpkqr"
    minSdk = 24
    targetSdk = 36
    versionCode = 1
    versionName = "1.0"

    buildConfigField("String", "CLOUD_RUN_BASE_URL", "\"\"")

    testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
  }

  // Do not remove or modify these signingConfigs. They are necessary for building, installing,
  // and updating Android apps in AI Studio.
  signingConfigs {
    create("release") {
      val keystorePath = System.getenv("KEYSTORE_PATH") ?: "${rootDir}/my-upload-key.jks"
      storeFile = file(keystorePath)
      storePassword = System.getenv("STORE_PASSWORD")
      keyAlias = "upload"
      keyPassword = System.getenv("KEY_PASSWORD")
    }
    create("debugConfig") {
      storeFile = file("${rootDir}/debug.keystore")
      storePassword = "android"
      keyAlias = "androiddebugkey"
      keyPassword = "android"
    }
  }

  buildTypes {
    release {
      isCrunchPngs = false
      isMinifyEnabled = false
      proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
      // Do not remove or modify this signingConfig assignment. It is necessary for Android apps in
      // AI Studio.
      signingConfig = signingConfigs.getByName("release")
    }
    debug {
      // Do not remove or modify this signingConfig assignment. It is necessary for Android apps in
      // AI Studio.
      signingConfig = signingConfigs.getByName("debugConfig")
    }
  }
  compileOptions {
    sourceCompatibility = JavaVersion.VERSION_11
    targetCompatibility = JavaVersion.VERSION_11
  }
  buildFeatures {
    compose = true
    buildConfig = true
  }
  testOptions { unitTests { isIncludeAndroidResources = true } }
  dependenciesInfo {
    includeInApk = false
    includeInBundle = true
  }
}

// Configure the Secrets Gradle Plugin to use .env and .env.example files
// to match the convention used in Web projects.
secrets {
  propertiesFileName = ".env"
  defaultPropertiesFileName = ".env.example"
  ignoreList.add("FIREBASE_APPCHECK_DEBUG_TOKEN")
  ignoreList.addAll(
      listOf(
          "A2UI_PORT",
          "AI_STUDIO_APPLET_ID",
          "ANTIGRAVITY_PROJECT_NAME",
          "APP_DEBUG",
          "APP_ENV",
          "APP_FAKER_LOCALE",
          "APP_FALLBACK_LOCALE",
          "APP_KEY",
          "APP_LOCALE",
          "APP_MAINTENANCE_DRIVER",
          "APP_NAME",
          "APP_TIMEZONE",
          "AWS_ACCESS_KEY_ID",
          "AWS_BUCKET",
          "AWS_DEFAULT_REGION",
          "AWS_SECRET_ACCESS_KEY",
          "AWS_USE_PATH_STYLE_ENDPOINT",
          "BCRYPT_ROUNDS",
          "BKASH_APP_KEY",
          "BKASH_APP_SECRET",
          "BKASH_BASE_URL",
          "BKASH_PASSWORD",
          "BKASH_USERNAME",
          "BROADCAST_CONNECTION",
          "CACHE_PREFIX",
          "CACHE_STORE",
          "CITUS_RADACCT_SHARDS",
          "CITUS_SUBSCRIBER_SHARDS",
          "CLOUD_RUN_SERVICE",
          "CRON_JOB_SECRET",
          "DB_CONNECTION",
          "DB_DATABASE",
          "DB_HOST",
          "DB_PASSWORD",
          "DB_PORT",
          "DB_USERNAME",
          "FILESYSTEM_DISK",
          "GCP_VPC_CONNECTOR",
          "GEMINI_FLASH_MODEL",
          "GEMINI_MODEL",
          "GEMINI_PRO_MODEL",
          "GENIEACS_NBI_URL",
          "GOOGLE_CLOUD_PROJECT",
          "GOOGLE_CLOUD_REGION",
          "LOG_CHANNEL",
          "LOG_DEPRECATIONS_CHANNEL",
          "LOG_LEVEL",
          "LOG_STACK",
          "MAIL_FROM_ADDRESS",
          "MAIL_FROM_NAME",
          "MAIL_HOST",
          "MAIL_MAILER",
          "MAIL_PASSWORD",
          "MAIL_PORT",
          "MAIL_SCHEME",
          "MAIL_USERNAME",
          "MEMCACHED_HOST",
          "NAGAD_MERCHANT_ID",
          "NAGAD_MERCHANT_KEY",
          "PHP_CLI_SERVER_WORKERS",
          "QUEUE_CONNECTION",
          "RABBITMQ_HOST",
          "RABBITMQ_PASSWORD",
          "RABBITMQ_PORT",
          "RABBITMQ_USER",
          "RADIUS_ACCT_PORT",
          "RADIUS_AUTH_PORT",
          "RADIUS_COA_PORT",
          "RADIUS_SECRET",
          "RADIUS_SERVER_HOST",
          "REACT_APP_A2UI_SERVER_URL",
          "REDIS_AUTH_CACHE_TTL",
          "REDIS_CLIENT",
          "REDIS_HOST",
          "REDIS_PASSWORD",
          "REDIS_POLICY",
          "REDIS_PORT",
          "ROUTEROS_API_HOST",
          "ROUTEROS_API_PASS",
          "ROUTEROS_API_PORT",
          "ROUTEROS_API_USER",
          "ROUTEROS_REST_PORT",
          "SESSION_DOMAIN",
          "SESSION_DRIVER",
          "SESSION_ENCRYPT",
          "SESSION_LIFETIME",
          "SESSION_PATH",
          "SSLCOMMERZ_STORE_ID",
          "SSLCOMMERZ_STORE_PASSWD",
          "VITE_APP_NAME",
          "WALLED_GARDEN_POOL"
      )
  )
}

googleServices { missingGoogleServicesStrategy = MissingGoogleServicesStrategy.WARN }

// Some unused dependencies are commented out below instead of being removed.
// This makes it easy to add them back in the future if needed.
dependencies {
  implementation(platform(libs.androidx.compose.bom))
  implementation(platform(libs.firebase.bom))
  // implementation(libs.accompanist.permissions)
  implementation(libs.androidx.activity.compose)
  // implementation(libs.androidx.camera.camera2)
  // implementation(libs.androidx.camera.core)
  // implementation(libs.androidx.camera.lifecycle)
  // implementation(libs.androidx.camera.view)
  implementation(libs.androidx.compose.material.icons.core)
  implementation(libs.androidx.compose.material.icons.extended)
  implementation(libs.androidx.compose.material3)
  implementation(libs.androidx.compose.ui)
  implementation(libs.androidx.compose.ui.graphics)
  implementation(libs.androidx.compose.ui.tooling.preview)
  implementation(libs.androidx.core.ktx)
  // implementation(libs.androidx.datastore.preferences)
  implementation(libs.androidx.lifecycle.runtime.compose)
  implementation(libs.androidx.lifecycle.runtime.ktx)
  implementation(libs.androidx.lifecycle.viewmodel.compose)
  implementation(libs.androidx.navigation.compose)
  implementation(libs.androidx.room.ktx)
  implementation(libs.androidx.room.runtime)
  // implementation(libs.coil.compose)
  implementation(libs.converter.moshi)
  implementation(libs.firebase.ai)
  // Uncomment to use Firestore:
  // implementation(libs.firebase.firestore)

  // Uncomment ALL FOUR of the following dependencies together to use Firebase Auth and Google
  // Sign-In via Credential Manager:
  // implementation(libs.firebase.auth)
  // implementation(libs.androidx.credentials)
  // implementation(libs.androidx.credentials.play.services)
  // implementation(libs.googleid)
  implementation(libs.firebase.appcheck.recaptcha)
  implementation(libs.firebase.appcheck.debug)
  implementation(libs.kotlinx.coroutines.android)
  implementation(libs.kotlinx.coroutines.core)
  implementation(libs.logging.interceptor)
  implementation(libs.moshi.kotlin)
  implementation(libs.okhttp)
  // implementation(libs.play.services.location)
  implementation(libs.retrofit)
  testImplementation(libs.androidx.compose.ui.test.junit4)
  testImplementation(libs.androidx.core)
  testImplementation(libs.androidx.junit)
  testImplementation(libs.junit)
  testImplementation(libs.kotlinx.coroutines.test)
  testImplementation(libs.robolectric)
  androidTestImplementation(platform(libs.androidx.compose.bom))
  androidTestImplementation(libs.androidx.compose.ui.test.junit4)
  androidTestImplementation(libs.androidx.espresso.core)
  androidTestImplementation(libs.androidx.junit)
  androidTestImplementation(libs.androidx.runner)
  debugImplementation(libs.androidx.compose.ui.test.manifest)
  debugImplementation(libs.androidx.compose.ui.tooling)
  "ksp"(libs.androidx.room.compiler)
  "ksp"(libs.moshi.kotlin.codegen)
}
