import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Properties;
import java.util.TreeMap;
import java.util.stream.Collectors;

/**
 * Evalúa los resultados (.jtl CSV) de la prueba de estrés del formulario de inscripción.
 *
 * Toma el tramo estable (después de RAMPA_SEG), compara rendimiento, errores y percentil 95
 * contra config.properties y escribe resumen.txt junto al .jtl. Termina con código 0 si la
 * prueba se aprueba y 1 si se rechaza.
 *
 * Uso (Java 11+, modo de archivo fuente):
 *   java Resumen.java resultados/AAAAMMDD-HHMMSS/resultados.jtl config.properties [CLAVE=valor ...]
 */
public class Resumen {

    record Muestra(long ts, long ms, String label, boolean ok, String code) {}

    public static void main(String[] args) throws IOException {
        if (args.length < 2) {
            System.err.println("Uso: java Resumen.java <resultados.jtl> <config.properties> [CLAVE=valor ...]");
            System.exit(2);
        }
        Path jtl = Path.of(args[0]);
        Properties p = new Properties();
        try (var r = Files.newBufferedReader(Path.of(args[1]), StandardCharsets.UTF_8)) {
            p.load(r);
        }
        for (int i = 2; i < args.length; i++) {
            int eq = args[i].indexOf('=');
            if (eq > 0) p.setProperty(args[i].substring(0, eq), args[i].substring(eq + 1));
        }
        double objetivo = num(p, "OBJETIVO_RPS", 15);
        long rampaMs = (long) (num(p, "RAMPA_SEG", 60) * 1000);
        double tolerancia = num(p, "TOLERANCIA_RPS_PCT", 5);
        double maxError = num(p, "MAX_ERROR_PCT", 1);
        double maxP95 = num(p, "MAX_P95_MS", 2000);

        List<Muestra> filas = leer(jtl);
        List<Muestra> form = filas.stream().filter(m -> m.label.startsWith("Formulario")).toList();
        List<Muestra> prep = filas.stream().filter(m -> m.label.startsWith("Preparaci")).toList();

        List<String> l = new ArrayList<>();
        boolean aprobada = true;
        if (form.isEmpty()) {
            l.add("No hubo peticiones del formulario. Revisa la preparación (convocatoria, cuentas y login).");
            aprobada = false;
        } else {
            long t0 = filas.stream().mapToLong(Muestra::ts).min().orElse(0);
            long inicio = t0 + rampaMs;
            long fin = form.stream().mapToLong(m -> m.ts + m.ms).max().orElse(inicio);
            List<Muestra> est = form.stream().filter(m -> m.ts >= inicio).toList();
            double seg = Math.max(1d, (fin - inicio) / 1000d);
            double rps = est.size() / seg;
            double errPct = est.isEmpty() ? 100d : 100d * est.stream().filter(m -> !m.ok).count() / est.size();
            long p95 = p95(est);
            long fallosPrep = prep.stream().filter(m -> !m.ok).count();
            double minRps = objetivo * (1 - tolerancia / 100d);

            l.add(String.format("Tramo estable: %.0f s (excluye %d s de rampa), %d peticiones del formulario", seg, rampaMs / 1000, est.size()));
            boolean[] ok = {
                rps >= minRps, errPct <= maxError, p95 <= maxP95, fallosPrep == 0,
            };
            l.add(marca(ok[0]) + String.format("Rendimiento %.2f peticiones/s (objetivo %.2f, mínimo %.2f)", rps, objetivo, minRps));
            l.add(marca(ok[1]) + String.format("Errores %.2f %% (máximo %.2f %%)", errPct, maxError));
            l.add(marca(ok[2]) + String.format("Percentil 95 %d ms (máximo %.0f ms)", p95, maxP95));
            l.add(marca(ok[3]) + String.format("Preparación: %d de %d peticiones fallidas (convocatoria/login)", fallosPrep, prep.size()));
            for (boolean b : ok) aprobada &= b;

            l.add("");
            l.add(String.format("%-46s %8s %8s %8s %8s", "Etiqueta (tramo estable)", "Total", "Err %", "Prom ms", "P95 ms"));
            Map<String, List<Muestra>> grupos = new TreeMap<>(est.stream().collect(Collectors.groupingBy(Muestra::label)));
            grupos.forEach((label, g) -> l.add(String.format("%-46s %8d %8.2f %8.0f %8d", label, g.size(),
                100d * g.stream().filter(m -> !m.ok).count() / g.size(), g.stream().mapToLong(Muestra::ms).average().orElse(0), p95(g))));
            Map<String, Long> codigos = new TreeMap<>(est.stream().filter(m -> !m.ok).collect(Collectors.groupingBy(Muestra::code, Collectors.counting())));
            if (!codigos.isEmpty()) {
                l.add("Códigos de error: " + codigos.entrySet().stream().map(e -> e.getKey() + "=" + e.getValue()).collect(Collectors.joining(", ")));
            }
        }
        l.add("");
        l.add("RESULTADO=" + (aprobada ? "APROBADA" : "RECHAZADA"));

        String texto = String.join(System.lineSeparator(), l) + System.lineSeparator();
        Path salida = jtl.toAbsolutePath().getParent().resolve("resumen.txt");
        Files.writeString(salida, texto, StandardCharsets.UTF_8);
        System.out.println();
        System.out.println("===== Resumen de la prueba de estrés =====");
        System.out.print(texto);
        System.exit(aprobada ? 0 : 1);
    }

    private static String marca(boolean ok) {
        return ok ? "[OK]    " : "[FALLA] ";
    }

    private static double num(Properties p, String clave, double defecto) {
        String v = p.getProperty(clave);
        return v == null || v.isBlank() ? defecto : Double.parseDouble(v.trim());
    }

    private static long p95(List<Muestra> l) {
        if (l.isEmpty()) return 0;
        long[] s = l.stream().mapToLong(Muestra::ms).sorted().toArray();
        return s[Math.max(0, (int) Math.ceil(s.length * 0.95) - 1)];
    }

    /** Lee el CSV de JMeter respetando comillas (los mensajes pueden contener comas o saltos de línea). */
    private static List<Muestra> leer(Path jtl) throws IOException {
        List<List<String>> registros = csv(Files.readString(jtl, StandardCharsets.UTF_8));
        List<Muestra> filas = new ArrayList<>();
        if (registros.isEmpty()) return filas;
        Map<String, Integer> col = new HashMap<>();
        List<String> cab = registros.get(0);
        for (int i = 0; i < cab.size(); i++) col.put(cab.get(i), i);
        for (String c : List.of("timeStamp", "elapsed", "label", "responseCode", "success")) {
            if (!col.containsKey(c)) throw new IllegalStateException("El .jtl no contiene la columna " + c + " (usa formato CSV con encabezados).");
        }
        for (List<String> r : registros.subList(1, registros.size())) {
            if (r.size() < cab.size()) continue;
            try {
                filas.add(new Muestra(Long.parseLong(r.get(col.get("timeStamp"))), Long.parseLong(r.get(col.get("elapsed"))),
                    r.get(col.get("label")), "true".equals(r.get(col.get("success"))), r.get(col.get("responseCode"))));
            } catch (NumberFormatException e) {
                // Fila incompleta (p. ej. prueba interrumpida): se ignora.
            }
        }
        return filas;
    }

    private static List<List<String>> csv(String texto) {
        List<List<String>> registros = new ArrayList<>();
        List<String> actual = new ArrayList<>();
        StringBuilder campo = new StringBuilder();
        boolean comillas = false;
        for (int i = 0; i < texto.length(); i++) {
            char c = texto.charAt(i);
            if (comillas) {
                if (c == '"' && i + 1 < texto.length() && texto.charAt(i + 1) == '"') { campo.append('"'); i++; }
                else if (c == '"') comillas = false;
                else campo.append(c);
            } else if (c == '"') {
                comillas = true;
            } else if (c == ',') {
                actual.add(campo.toString()); campo.setLength(0);
            } else if (c == '\n' || c == '\r') {
                if (c == '\r' && i + 1 < texto.length() && texto.charAt(i + 1) == '\n') i++;
                actual.add(campo.toString()); campo.setLength(0);
                if (!(actual.size() == 1 && actual.get(0).isEmpty())) registros.add(actual);
                actual = new ArrayList<>();
            } else {
                campo.append(c);
            }
        }
        if (campo.length() > 0 || !actual.isEmpty()) { actual.add(campo.toString()); registros.add(actual); }
        return registros;
    }
}
