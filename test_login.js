async function testLogin() {
  try {
    console.log('Probando login con credenciales de admin...');
    const response = await fetch('http://127.0.0.1:8000/api/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        dni: process.env.BOTICA_TEST_DNI || '00000000',
        password: process.env.BOTICA_TEST_PASSWORD || 'change_me'
      })
    });

    const data = await response.json();

    if (response.ok) {
      console.log('Login exitoso!');
      console.log('Usuario:', data.user.nombre);
      console.log('Rol:', data.user.rol);
      console.log('Token:', data.token.substring(0, 50) + '...');
    } else {
      console.error('Error en login:', data);
    }

  } catch (error) {
    console.error('Error de conexión:', error.message);
  }
}

testLogin();