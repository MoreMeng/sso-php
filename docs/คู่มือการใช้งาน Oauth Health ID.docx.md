

**คู่มือการเชื่อมต่อ OAuth**  
**ของระบบ Health ID**

### **การใช้งาน OAuth ของระบบหมอพร้อมดิจิทัลไอดี (Health ID)**

1. ### **System Endpoint**

| URL-UAT (สำหรับทดสอบ) | https://uat-moph.id.th |
| :---- | :---- |
| URL-PRD (สำหรับใช้งานจริง) | https://moph.id.th |

   

   

   1. #### ***ขั้นตอนการเชื่อมต่อ***	

        
1. สร้างปุ่ม Login with Health ID  บนหน้า Web Application/Mobile Application ของคุณ  
2. แนบ Link Login URL ของ Health ID เข้าที่ปุ่มข้อ 1.1.1 โดย URL มีรูปแบบดังนี้ (รายละเอียดเพิ่มเติมในข้อที่ 1.2  Authentication Request)

| {URL}/oauth/redirect?client\_id={client\_id}\&redirect\_uri={redirect\_uri}\&response\_type=code |
| :---: |

3. เมื่อผู้ใช้งานดำเนินการเข้าสู่ระบบสำเร็จ Health ID จะทำการ redirect กลับไปยัง Redirect URL ที่ Service ได้ทำการลงทะเบียนไว้ โดยจะแนบ Code ไว้ที่ Parameters เพื่อให้นำไปใช้ตรวจสอบการเข้าสู่ระบบ  
4. Frontend ของ Service ต้องทำการอ่านค่า code จาก URL แล้วส่งให้ Backend ของ Service  
5. Backend ของ Service นำ code ที่ได้ ส่งมาตรวจสอบกับ Health ID โดยเรียก API ในข้อที่ 1.3 Token Request  
6. เมื่อได้  access\_token ของ Health ID  ไปจะสามารถเรียก API ในข้อ 1.4 Get user profile ที่ใช้ access\_token ในการเข้าถึงข้อมูลของผู้ใช้งานได้  
     
   

   2. #### ***Authentication Request***	

Web Application/Mobile Application ของ RP จะส่ง authentication request ด้วย{HealthID-URL}/oauth/redirect?client\_id={client\_id}\&redirect\_uri={redirect\_uri}\&response\_type=code โดยระบุ client\_id และ redirect\_uri ที่ได้ลงทะเบียนไว้กับ Health ID สำเร็จแล้ว

| GET | {HealthID-URL}/oauth/redirect |
| :---- | :---- |

**Request**  
**Request Params** : 

| พารามิเตอร์ (Parameters) | จำเป็น (Requires) | ประเภทข้อมูล (type) | คำอธิบาย (Description) |
| ----- | :---: | :---: | ----- |
| client\_id | Y | string | client id ที่ได้รับหลังจากการลงทะเบียนกับ Health ID |
| redirect\_uri | Y | string | redirect uri ที่ทะเบียนกับ Health ID |
| response\_type | Y | string | ระบุคำว่า “code” |
| state | N | string | random string ใช้ในการตรวจสอบความสัมพันธ์ระหว่าง request จาก RP กับ response |

3. #### ***Token Request***	

เมื่อ Service ของท่านได้มีการเรียกใช้งานหน้าจอ Login With Health ID และผู้ใช้งาน กรอกข้อมูลเพื่อเข้าสู่ระบบสำเร็จแล้ว ระบบจะ Redirect พร้อมกับ Return Code ให้ ​Service ของท่านผ่าน URL ท่านสามารถนำ Code ที่ได้ มาใช้สำหรับขอ access token ของผู้ใช้งานจาก API ดังนี้

| POST | {HealthID-URL}/api/v1/token |
| :---- | :---- |

**Request**  
**Request Header:**   
**Content-type: application/x-www-form-urlencoded**  
**Request Body:** 

| พารามิเตอร์ (Parameters) | จำเป็น (Requires) | ประเภทข้อมูล (type) | คำอธิบาย (Description) |
| ----- | :---: | :---: | ----- |
| grant\_type | Y | string | ระบุคำว่า “authorization\_code” |
| code | Y | string | code ที่ได้รับจาก redirect url ที่ระบบ return ให้หลังจากผู้ใช้งานเข้าสู่ระบบ |
| redirect\_uri | Y | string | redirect url ที่ต้องการให้ return กลับไป จะต้องตรงกันกับ authentication request |
| client\_id | Y | string | client id ที่ได้รับหลังจากการลงทะเบียนกับ Health ID |
| client\_secret | Y | string | client secret ที่ได้รับหลังจากการลงทะเบียนกับ Health ID |

**Response**  
Response Type: application/json

| พารามิเตอร์ (Parameters) | ประเภทข้อมูล (type) | คำอธิบาย (Description) |
| :---- | :---: | ----- |
| token\_type | string | ประเภทของ token |
| expires\_in | Int | เวลาหมดอายุของ token |
| access\_token | string | token ที่ใช้ในการยืนยันตัวตน |
| expiration\_date | string | วันที่และเวลาที่หมดอายุของ token |
| account\_id | string | Identities Number ที่ระบบสร้างให้อัตโนมัติโดยแต่ละคน (ID) ไม่ซ้ำกัน |

**ตัวอย่าง Response Body:**  
	**200 OK**  
{  
    	"status": "success",  
    	"data": {  
"access\_token": "eyJ0eXAiOiJKV1QiLCJhbGci….",  
"token\_type": "Bearer",  
"expires\_in": 31535998,  
"account\_id": "165902799049006"  
},  
    	"message": "You logged in successfully"  
}

**Error code**

| Code | Message | Description |
| :---: | ----- | ----- |
| 401 | Credential is required | จำเป็นต้องใส่ข้อมูล |
| 422 | Access grant has denied | สิทธิ์การเข้าถึงถูกปฏิเสธ |
| 422 | Code is invalid | โค้ดไม่ถูกต้อง |
| 422 | Redirect uri is invalid | Redirect uri ไม่ตรงกัน |
| 422 | Code and Client ID not match. | โค้ดกับ client id ไม่ตรง |
| 422 | Code has been expired | โค้ดหมดอายุ |
| 500 | Server error | เซิฟเวอร์มีปัญหา |

4. #### ***API สำหรับ Get user profile***

เมื่อ Service ของท่าน ได้รับ access token ของผู้ใช้งานมาแล้ว สามารถนำมาใช้ในการขอข้อมูลส่วนตัวของผู้ใช้งานจาก API ดังนี้

| GET | {HealthID-URL}/go-api/v1/profile |
| :---- | :---- |

**Request**  
Request Header:   
Content-type: application/json  
Authorization: Bearer {{ healthid\_access\_token จาก Response ของ API ข้อที่ 1.3 }}

**Response**  
Response Type: application/json

| พารามิเตอร์ (Parameters) | ประเภทข้อมูล (type) | คำอธิบาย (Description) |
| :---- | ----- | ----- |
| account\_id | String | เป็น Identities Number ที่ระบบสร้างให้อัตโนมัติโดยแต่ละคน (ID) ไม่ซ้ำกัน |
| first\_name\_th | String | ชื่อจริงภาษาไทย |
| middle\_name\_th | String | ชื่อกลางภาษาไทย |
| last\_name\_th | String | นามสกุลภาษาไทย |
| first\_name\_eng | String | ชื่อจริงภาษาอังกฤษ |
| middle\_name\_eng | String | ชื่อกลางภาษาอังกฤษ |
| last\_name\_eng | String | นามสกุลภาษาอังกฤษ |
| account\_title\_th | String | คำนำหน้าชื่อภาษาไทย |
| account\_title\_eng | String | คำนำหน้าชื่อภาษาอังกฤษ |
| id\_card\_type | String | ชนิดของบัตรประชน |
| id\_card\_num | String | เลขบัตรประชาชน |
| hash\_id\_card\_num | String | เลขบัตรประชาชนที่ Hash ด้วย SHA256 |
| account\_sub\_category | String | ชนิดของบัญชี (Thai, Foreigner) |
| birth\_date | date | วันเดือนปีเกิด |
| mobile\_number | String | เบอร์โทรศัพท์ |
| gender\_th | String | เพศภาษาไทย |
| gender\_eng | String | เพศภาษาอังกฤษ  |
| status\_dt | timestamps | วันที่มีการเปลี่ยนแปลงข้อมูลของผู้ใช้ |
| register\_dt | timestamps | วันที่ผู้ใช้ลงทะเบียน |
| addresses\[\] |  |  |
| address | String | ที่อยู่ |
| sub\_district | String | แขวง/ตำบล |
| district | String | เขต/อำเภอ |
| province | String | จังหวัด |
| type | String | ประเภทของที่อยู่ |
| IAL |  |  |
| level | float | ระดับความน่าเชื่อถือของการพิสูจน์และยืนยันตัวตนของผู้ใช้ |
| auth\_method\_name | String | วิธีที่ใช้ในการยืนยันตัวตน |
| verified\_at | String | วันและเวลาที่ผู้ใช้ยืนยันตัวตน |
| birth\_date\_array | array | วัน เดือน ปีเกิดของผู้ใช้ |
| id\_card\_encrypt | String | เลขบัตรประชาชนที่เข้ารหัส |
| message | String | ข้อความตอบกลับจาก api |
| status\_code | Int | status code จาก api |

**ตัวอย่าง Response Body:**  
	**200 OK**  
{  
    "status": "success",  
    "data": {  
        "first\_name\_th": "สมหญิง",  
        "middle\_name\_th": "",  
        "last\_name\_th": "เติมใจ",  
        "first\_name\_eng": "Somying",  
        "middle\_name\_eng": "",  
        "last\_name\_eng": "Termjai",  
        "account\_title\_th": "น.ส.",  
        "account\_title\_eng": "Miss",  
        "id\_card\_type": "ID\_CARD",  
        "id\_card\_num": "xxxxxx00939393",  
        "hash\_id\_card\_num": "9fc03a0047e71ddfa56b7bfb1e47c4fc6c4a",  
        "account\_sub\_category": "Thai",  
        "birth\_date": "25/12/1990",  
        "status\_dt": "2025-01-08T11:51:34.000000Z",  
        "register\_dt": "2025-01-08T11:51:34.000000Z",  
        "created\_at": "2024-11-19T10:51:34.000000Z",  
        "updated\_at": "2025-01-21T14:54:00.000000Z",  
        "gender\_th": "หญิง",  
        "gender\_eng": "female",  
        "province": "กรุงเทพมหานคร",  
        "addresses": \[  
            {  
                "address": "1 หมู่ที่ 19",  
                "sub\_district": "ห้วยขวาง",  
                "district": "ห้วยขวาง",  
                "province": "กรุงเทพมหานคร",  
                "type": "ที่อยู่อื่น ๆ"  
            }  
        \],  
        "account\_id": "1731xxxxxxxxxx92",  
        "mobile\_number": "08xxxxxxxx",  
        "ial": {  
            "level": 1.3,  
            "auth\_method\_name": "Dipchip",  
            "verified\_at": "23/01/25 18:20"  
        },  
        "birth\_date\_array": \[  
            "25",  
            "12",  
            "1990"  
        \],  
        "id\_card\_encrypt": "ZSI6ImEwdTVZSWY5cG05T0dLaEphSVBiVlE9PSJ9"  
    },  
    "message": "OK",  
    "status\_code": 200  
}

**Error code**

| Code | Message | Description |
| :---: | ----- | ----- |
| 400 | Can not get information. | ไม่สามารถดูข้อมูลได้ |
| 500 | Server error | เซิฟเวอร์มีปัญหา |


5. #### ***API สำหรับขอ Public Key ของ Health ID***

| GET | {HealthID-URL}/api/v1/oauth/public-key |
| :---- | :---- |

**Headers**  
Request Header: 

| ชื่อของ Header (Key) | จำเป็น (Requires) | ค่าของ Header (Value) | คำอธิบาย (Description) |
| ----- | :---: | :---: | ----- |
| Content-type |  | application/json |  |
| client-id | Y | string | Client-ID ที่ได้รับจากระบบ Health ID |
| secret-key | Y | string | Secret-Key ที่ได้รับจากระบบHealth ID |

**Response**  
Response Type: plain/text

| พารามิเตอร์ (Parameters) | ประเภทข้อมูล (type) | คำอธิบาย (Description) |
| :---- | :---: | ----- |
| public key | string | Public Key |

**ตัวอย่าง Response Body:**

**200 OK**   
\-----BEGIN PUBLIC KEY-----  
XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX  
OpnddMRKQ5vImHrx7kiJw1p0FD4/pUm/uv8rw2SEsse5aVpp35k9N4CS6  
bqZdDgelSwB/2Z0dtpaLTuwqx  
XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX  
\-----END PUBLIC KEY-----

**Error code**

| Code | message | message\_th |
| :---: | ----- | ----- |
| 422 | Access grant has denied. | Client Id, Secret Key ไม่ถูกต้อง |

