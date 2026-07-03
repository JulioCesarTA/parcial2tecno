// Normaliza texto para comparar sin importar tildes/mayúsculas
// ("estadis" debe encontrar "Estadísticas").
const RANGO_DIACRITICOS = new RegExp('[' + String.fromCharCode(0x0300) + '-' + String.fromCharCode(0x036f) + ']', 'g');

export function normalizar(valor) {
  return (valor ?? '')
    .toString()
    .normalize('NFD')
    .replace(RANGO_DIACRITICOS, '')
    .toLowerCase()
    .trim();
}

export function coincide(texto, termino) {
  const t = normalizar(termino);
  if (!t) return true;
  return normalizar(texto).includes(t);
}
