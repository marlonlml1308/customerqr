<!DOCTYPE html>
<html lang="es" class="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Formulario de Cliente</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.key') }}"></script>
</head>

<body class="bg-gray-900 text-white">
  <div class="max-w-md mx-auto mt-10 p-6">
    <!-- Logo -->
    <div class="flex justify-center mb-4">
      <img src="https://sotocoffee.com.co/wp-content/uploads/2020/11/SotoCoffeeLogoBlanco-300x300.png" alt="Logo"
        class="h-32">
    </div>

    <h1 class="text-2xl font-bold text-center mb-6">Registrar Cliente</h1>

    @if(session('success'))
      <p class="bg-green-100 text-green-700 p-3 rounded mb-4 text-center">
        {{ session('success') }}
      </p>
    @endif

    @if($errors->any())
      <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
        <ul class="list-disc pl-5">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('clientes.store') }}" method="POST" id="demo-form">
      @csrf

      <!-- Campo: Número de Documento -->
      <div class="relative mb-6">
        <label for="numero_documento" class="flex items-center mb-2 text-gray-300 text-sm font-medium">
          Número de Documento
        </label>
        <div class="relative text-gray-500 focus-within:text-gray-300">
          <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="stroke-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
              xmlns="http://www.w3.org/2000/svg">
              <path
                d="M6 2H14L20 8V22C20 22.5304 19.7893 23.0391 19.4142 23.4142C19.0391 23.7893 18.5304 24 18 24H6C5.46957 24 4.96086 23.7893 4.58579 23.4142C4.21071 23.0391 4 22.5304 4 22V4C4 3.46957 4.21071 2.96086 4.58579 2.58579C4.96086 2.21071 5.46957 2 6 2Z"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M14 2V8H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </div>
          <input type="text" name="numero_documento" id="numero_documento" value="{{ old('numero_documento') }}"
            class="block w-full h-11 pr-5 pl-12 py-2.5 text-base font-normal shadow-xs text-gray-100 bg-gray-800 border border-gray-600 rounded-full placeholder-gray-500 focus:outline-none"
            placeholder="Ingrese número de documento" required>
        </div>
      </div>

      <!-- Campo: Nombre -->
      <div class="relative mb-6">
        <label for="nombre" class="flex items-center mb-2 text-gray-300 text-sm font-medium">
          Nombre
        </label>
        <div class="relative text-gray-500 focus-within:text-gray-300">
          <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="stroke-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
              xmlns="http://www.w3.org/2000/svg">
              <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" stroke="currentColor"
                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M19 21v-2c0-2.761-2.239-5-5-5H10c-2.761 0-5 2.239-5 5v2" stroke="currentColor" stroke-width="1.5"
                stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}"
            class="block w-full h-11 pr-5 pl-12 py-2.5 text-base font-normal shadow-xs text-gray-100 bg-gray-800 border border-gray-600 rounded-full placeholder-gray-500 focus:outline-none"
            placeholder="Ingrese nombre" maxlength="50" required>
        </div>
      </div>

      <!-- Campo: Tipo de Documento -->
      <div class="relative mb-6">
        <label for="tipo_documento" class="flex items-center mb-2 text-gray-300 text-sm font-medium">
          Tipo de Documento
        </label>
        <div class="relative text-gray-500 focus-within:text-gray-300">
          <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="stroke-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
              xmlns="http://www.w3.org/2000/svg">
              <path d="M7 10L12 15L17 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </div>
          <select name="tipo_documento" id="tipo_documento"
            class="block w-full h-11 pr-5 pl-12 py-2.5 text-base font-normal shadow-xs text-gray-100 bg-gray-800 border border-gray-600 rounded-full placeholder-gray-500 focus:outline-none"
            required>
            <option value="31">NIT</option>
            <option value="13">Cédula de Ciudadanía</option>
            <option value="22">Cédula Extranjería</option>
            <option value="21">Tarjeta Extranjería</option>
            <option value="41">Pasaporte</option>
            <option value="42">Documento extranjero</option>
            <option value="50">NIT Extranjero</option>
            <option value="12">Tarjeta de identidad</option>
            <option value="91">NUIP</option>
            <option value="47">PEP</option>
            <option value="11">Registro Civil</option>
          </select>
        </div>
      </div>

      <!-- Campo: Correo Electrónico -->
      <div class="relative mb-6">
        <label for="correo" class="flex items-center mb-2 text-gray-300 text-sm font-medium">
          Correo Electrónico
        </label>
        <div class="relative text-gray-500 focus-within:text-gray-300">
          <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="stroke-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
              xmlns="http://www.w3.org/2000/svg">
              <path
                d="M4 4H20C21.1046 4 22 4.89543 22 6V18C22 19.1046 21.1046 20 20 20H4C2.89543 20 2 19.1046 2 18V6C2 4.89543 2.89543 4 4 4Z"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </div>
          <input type="email" name="correo" id="correo" value="{{ old('correo') }}"
            class="block w-full h-11 pr-5 pl-12 py-2.5 text-base font-normal shadow-xs text-gray-100 bg-gray-800 border border-gray-600 rounded-full placeholder-gray-500 focus:outline-none"
            placeholder="Ingrese correo electrónico" pattern="[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}" required>
        </div>
      </div>

      <!-- Campo oculto para reCAPTCHA -->
      <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

      <!-- Botón de envío -->
      <div class="flex items-center justify-center">
        <button type="submit" id="submit-btn"
          class="w-52 h-12 shadow-sm rounded-full bg-indigo-600 hover:bg-indigo-800 transition-all duration-700 text-white text-base font-semibold leading-7">
          Guardar
        </button>
      </div>
    </form>
  </div>

  <script>
    const LOG_PREFIX = '[CustomerForm]';

    // Autocompletar cuando se ingresa un documento
    const docInput = document.getElementById('numero_documento');
    docInput.addEventListener('blur', async function () {
      const docNumber = this.value;
      if (!docNumber) return;

      console.log(`${LOG_PREFIX} Buscando cliente con documento: ${docNumber}`);

      try {
        const response = await fetch(`/proxy/get-customer?document=${encodeURIComponent(docNumber)}`);

        if (!response.ok) {
          console.error(`${LOG_PREFIX} Error en búsqueda. Status:`, response.status);
          return;
        }

        const result = await response.json();
        console.log(`${LOG_PREFIX} Respuesta:`, result);

        if (result.found && result.data) {
          document.getElementById('nombre').value = result.data.nombre || '';
          document.getElementById('correo').value = result.data.correo || '';
          console.log(`${LOG_PREFIX} Formulario autocompletado`);
        } else {
          console.log(`${LOG_PREFIX} Cliente no encontrado`);
        }
      } catch (error) {
        console.error(`${LOG_PREFIX} Error:`, error);
      }
    });

    // Manejar el submit del formulario con reCAPTCHA
    document.getElementById('demo-form').addEventListener('submit', function (e) {
      e.preventDefault();
      const form = this;
      const submitBtn = document.getElementById('submit-btn');

      console.log(`${LOG_PREFIX} Iniciando envío del formulario`);
      submitBtn.disabled = true;
      submitBtn.textContent = 'Procesando...';

      grecaptcha.ready(function () {
        grecaptcha.execute('{{ config('services.recaptcha.key') }}', { action: 'submit' })
          .then(function (token) {
            console.log(`${LOG_PREFIX} Token reCAPTCHA obtenido`);
            document.getElementById('g-recaptcha-response').value = token;
            form.submit();
          })
          .catch(function (error) {
            console.error(`${LOG_PREFIX} Error obteniendo token reCAPTCHA:`, error);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Guardar';
            alert('Error con el captcha. Por favor, recarga la página.');
          });
      });
    });
  </script>
</body>

</html>