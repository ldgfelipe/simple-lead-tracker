<?php
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Simulador Pipedrive SLT</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #fff; padding: 20px; color: #3d3d3d; }
        .eyyRCl { max-width: 800px; margin: 0 auto; border: 1px solid #e5e5e5; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h1 { font-size: 20px; margin-bottom: 10px; color: #262626; line-height: 1.2; }
        .hsgvN { font-size: 13px; margin-bottom: 15px; color: #6a6a6a; line-height: 1.4; }
        .dHgxMY { margin-bottom: 15px; }
        label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; border: 1px solid #bcc0c3; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        input:focus { border-color: #08a742; outline: none; box-shadow: 0 0 0 2px rgba(8,167,66,0.1); }
        .eWbjfu { 
            background: #08a742; color: white; border: none; padding: 12px 30px; 
            border-radius: 4px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 20px;
            font-size: 16px; transition: background 0.2s;
        }
        .eWbjfu:hover { background: #068a36; }
        .footer-note { font-size: 11px; color: #999; margin-top: 15px; text-align: center; }
    </style>
</head>
<body>
    <div id="app">
        <div class="eyyRCl">
            <form id="lead-form">
                <h1>Request a Quote...</h1>
                <div class="hsgvN">Simulador de Pipedrive para pruebas del Lead Tracker.</div>
                
                <div class="dHgxMY">
                    <label>Name *</label>
                    <input name="name" id="name" type="text" required placeholder="Full Name" onfocus="simularInteraccion()">
                </div>
                
                <div class="dHgxMY">
                    <label>Email *</label>
                    <input name="email" id="email" type="email" required placeholder="email@example.com">
                </div>

                <div class="dHgxMY">
                    <label>Phone *</label>
                    <input name="phone" id="phone" type="tel" required placeholder="(555) 000-0000">
                </div>

                <div class="dHgxMY">
                    <label>Project Address *</label>
                    <input name="address" id="address" type="text" required placeholder="123 Street, CA">
                </div>

                <button type="submit" class="eWbjfu">Submit Quote Request</button>
            </form>
            <div class="footer-note">Simulación local - Sin conexión externa</div>
        </div>
    </div>

    <script>
        // Generamos un ID dinámico para la URL de simulación
        const randomID = Math.random().toString(36).substring(2, 12);
        const pipedriveBaseUrl = `https://webforms.pipedrive.com/f/${randomID}`;

        // Simula la acción inicial de interacción (interacted)
        function simularInteraccion() {
            if (!window.interactedSent) {
                fetch(`${pipedriveBaseUrl}/interacted`, {
                    method: 'POST',
                    mode: 'no-cors',
                    body: JSON.stringify({ event: 'focus', timestamp: Date.now() })
                });
                window.interactedSent = true;
                console.log("MOCK: Evento 'interacted' disparado.");
            }
        }

        document.getElementById('lead-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const leadName = document.getElementById('name').value;
            const leadEmail = document.getElementById('email').value;
            const leadPhone = document.getElementById('phone').value;
            const leadAddress = document.getElementById('address').value;

            // 1. Simulación de la petición de RED (Fetch) que Pipedrive hace realmente
            const pipedrivePayload = {
                values: {
                    "V2ViRm9ybUNhcHR1cmVCbG9jazpjZjI1NTEzMi0xMGY0": leadName,
                    "V2ViRm9ybUNhcHR1cmVCbG9jazpjZjI1NTEzMy0xMGY0": leadEmail,
                    "V2ViRm9ybUNhcHR1cmVCbG9jazpjZjI1NTEzNC0xMGY0": leadPhone,
                    "V2ViRm9ybUNhcHR1cmVCbG9jazpjZjI1NTEzNS0xMGY0": leadAddress,
                    "V2ViRm9ybVN1Ym1pdEJsb2Nr": "mock_token_" + btoa(Math.random().toString())
                }
            };

            console.log("MOCK: Enviando payload de red a:", pipedriveBaseUrl);

            fetch(pipedriveBaseUrl, {
                method: 'POST',
                mode: 'no-cors',
                body: JSON.stringify(pipedrivePayload)
            });

            // 2. Envío de postMessage para compatibilidad con el listener del tracker
            const messagePayload = {
                event: 'form-submitted',
                type: 'form-submitted',
                data: {
                    name: leadName,
                    email: leadEmail
                }
            };

            window.parent.postMessage(messagePayload, "*");
            window.parent.postMessage(JSON.stringify(messagePayload), "*");
            
            console.log("MOCK: Señales enviadas. Redirigiendo...");

            // Redirección al Thank You
            setTimeout(function() {
                try {
                    window.parent.location.href = '/thank-you';
                } catch(err) {
                    window.location.href = '/thank-you';
                }
            }, 800);
        });
    </script>
</body>
</html>